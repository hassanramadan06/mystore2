<?php
// Checkout + order history. Uses an explicit transaction:
// (1) lock products, (2) decrement stock, (3) create order + items,
// (4) generate stub PaymentIntent, (5) clear cart. Everything commits or rolls back together.

namespace MyStore\Controllers;

use MyStore\Database;
use MyStore\Helpers;

class OrdersController
{
    // Status mirrors the .NET enum: 0=Pending, 1=Paid, 2=Shipped, 3=Delivered, 4=Cancelled.
    private const STATUS_PENDING = 0;

    public function checkout(): void
    {
        $user = Helpers::requireUser();
        $body = Helpers::body();

        $address    = Helpers::require($body, 'shippingAddress', 'string', 300);
        $city       = Helpers::require($body, 'city', 'string', 60);
        $postalCode = Helpers::require($body, 'postalCode', 'string', 20);
        $country    = Helpers::require($body, 'country', 'string', 60);

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $items = $this->resolveItems($pdo, (int)$user['id'], $body['items'] ?? []);
            if (empty($items)) Helpers::error('Cart is empty', 422);

            // Lock products and verify stock atomically.
            $ids = array_column($items, 'productId');
            $place = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare("SELECT id, name, price, stock FROM products WHERE id IN ($place) FOR UPDATE");
            $stmt->execute($ids);
            $products = [];
            foreach ($stmt->fetchAll() as $p) $products[(int)$p['id']] = $p;

            $total = 0.0;
            foreach ($items as &$it) {
                $p = $products[$it['productId']] ?? null;
                if (!$p)                  Helpers::error('Product ' . $it['productId'] . ' not found', 404);
                if ($p['stock'] < $it['quantity']) {
                    Helpers::error('Insufficient stock for ' . $p['name'], 409);
                }
                $it['unitPrice'] = (float)$p['price'];
                $it['name']      = $p['name'];
                $total += $it['unitPrice'] * $it['quantity'];
            }
            unset($it);

            // Decrement stock.
            $dec = $pdo->prepare('UPDATE products SET stock = stock - ? WHERE id = ?');
            foreach ($items as $it) $dec->execute([$it['quantity'], $it['productId']]);

            // Create order.
            $insOrder = $pdo->prepare(
                'INSERT INTO orders (user_id, total, status, shipping_address, city, postal_code, country)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $insOrder->execute([$user['id'], $total, self::STATUS_PENDING, $address, $city, $postalCode, $country]);
            $orderId = (int)$pdo->lastInsertId();

            $insItem = $pdo->prepare(
                'INSERT INTO order_items (order_id, product_id, quantity, unit_price) VALUES (?, ?, ?, ?)'
            );
            foreach ($items as $it) {
                $insItem->execute([$orderId, $it['productId'], $it['quantity'], $it['unitPrice']]);
            }

            // Stub PaymentIntent — same shape as the .NET stub.
            $paymentIntentId = sprintf('pi_stub_%d_%s', $orderId, bin2hex(random_bytes(8)));
            $clientSecret    = $paymentIntentId . '_secret_' . bin2hex(random_bytes(8));
            $pdo->prepare('UPDATE orders SET payment_intent_id = ? WHERE id = ?')
                ->execute([$paymentIntentId, $orderId]);

            // Clear the user's cart.
            $pdo->prepare('DELETE FROM cart_items WHERE user_id = ?')->execute([$user['id']]);

            $pdo->commit();

            Helpers::json([
                'order' => $this->loadOrder($orderId, (int)$user['id']),
                'clientSecret'   => $clientSecret,
                'publishableKey' => $GLOBALS['MYSTORE_CONFIG']['payment']['publishable_key'] ?? '',
            ], 201);
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public function mine(): void
    {
        $user = Helpers::requireUser();
        $stmt = Database::pdo()->prepare('SELECT id FROM orders WHERE user_id = ? ORDER BY created_at DESC');
        $stmt->execute([$user['id']]);
        $list = [];
        foreach ($stmt->fetchAll() as $row) {
            $list[] = $this->loadOrder((int)$row['id'], (int)$user['id']);
        }
        Helpers::json($list);
    }

    public function get(int $id): void
    {
        $user = Helpers::requireUser();
        $order = $this->loadOrder($id, (int)$user['id']);
        Helpers::json($order);
    }

    public function listAll(): void
    {
        Helpers::requireAdmin();
        $stmt = Database::pdo()->query('SELECT id, user_id FROM orders ORDER BY created_at DESC');
        $list = [];
        foreach ($stmt->fetchAll() as $row) {
            $list[] = $this->loadOrder((int)$row['id'], (int)$row['user_id'], true);
        }
        Helpers::json($list);
    }

    public function updateStatus(int $id): void
    {
        Helpers::requireAdmin();
        $body = Helpers::body();
        // Accept either an integer (preferred) or a string status name.
        $status = $body['status'] ?? $body[0] ?? null;
        if (is_string($status)) {
            $map = ['Pending' => 0, 'Paid' => 1, 'Shipped' => 2, 'Delivered' => 3, 'Cancelled' => 4];
            $status = $map[$status] ?? null;
        }
        if (!is_int($status) || $status < 0 || $status > 4) {
            Helpers::error('Invalid status', 422);
        }
        $stmt = Database::pdo()->prepare('UPDATE orders SET status = ? WHERE id = ?');
        $stmt->execute([$status, $id]);
        if ($stmt->rowCount() === 0) Helpers::error('Order not found', 404);

        // Reload and return — we don't know the user_id without an extra lookup,
        // so do that and pass admin=true to skip the ownership check.
        $row = Database::pdo()->prepare('SELECT user_id FROM orders WHERE id = ?');
        $row->execute([$id]);
        $userId = (int)$row->fetchColumn();
        Helpers::json($this->loadOrder($id, $userId, true));
    }

    /**
     * Load an order with its items, validating that it belongs to the requesting user
     * unless $admin is true.
     */
    private function loadOrder(int $id, int $userId, bool $admin = false): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT id, user_id, total, status, shipping_address, city, postal_code, country,
                    payment_intent_id, created_at
             FROM orders WHERE id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) Helpers::error('Order not found', 404);
        if (!$admin && (int)$row['user_id'] !== $userId) Helpers::error('Order not found', 404);

        $items = Database::pdo()->prepare(
            'SELECT oi.product_id, oi.quantity, oi.unit_price,
                    p.name AS product_name, p.image_url AS product_image_url
             FROM order_items oi
             JOIN products p ON p.id = oi.product_id
             WHERE oi.order_id = ?'
        );
        $items->execute([$id]);

        return [
            'id'              => (int)$row['id'],
            'userId'          => (int)$row['user_id'],
            'total'           => (float)$row['total'],
            'status'          => (int)$row['status'],
            'shippingAddress' => $row['shipping_address'],
            'city'            => $row['city'],
            'postalCode'      => $row['postal_code'],
            'country'         => $row['country'],
            'paymentIntentId' => $row['payment_intent_id'],
            // ISO-8601 to match the .NET `DateTime` JSON output.
            'createdAt'       => date('c', strtotime($row['created_at'])),
            'items'           => array_map(function ($i) {
                $line = (float)$i['unit_price'] * (int)$i['quantity'];
                return [
                    'productId'        => (int)$i['product_id'],
                    'productName'      => $i['product_name'],
                    'productImageUrl'  => $i['product_image_url'],
                    'quantity'         => (int)$i['quantity'],
                    'unitPrice'        => (float)$i['unit_price'],
                    'lineTotal'        => $line,
                ];
            }, $items->fetchAll()),
        ];
    }

    /**
     * Resolves the line items for a checkout: explicit `items` from the body
     * if provided, otherwise the user's current cart.
     */
    private function resolveItems(\PDO $pdo, int $userId, $explicit): array
    {
        if (is_array($explicit) && count($explicit) > 0) {
            $out = [];
            foreach ($explicit as $i) {
                if (!isset($i['productId'])) Helpers::error('items[].productId required', 422);
                $out[] = [
                    'productId' => (int)$i['productId'],
                    'quantity'  => max(1, (int)($i['quantity'] ?? 1)),
                ];
            }
            return $out;
        }
        $stmt = $pdo->prepare('SELECT product_id, quantity FROM cart_items WHERE user_id = ?');
        $stmt->execute([$userId]);
        $out = [];
        foreach ($stmt->fetchAll() as $r) {
            $out[] = ['productId' => (int)$r['product_id'], 'quantity' => (int)$r['quantity']];
        }
        return $out;
    }
}
