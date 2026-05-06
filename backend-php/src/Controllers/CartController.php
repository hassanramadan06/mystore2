<?php
// Server-side cart for authenticated users.
// All endpoints require a valid Bearer token; the cart is keyed by user id.

namespace MyStore\Controllers;

use MyStore\Database;
use MyStore\Helpers;

class CartController
{
    public function get(): void
    {
        $user = Helpers::requireUser();
        Helpers::json($this->cartFor((int)$user['id']));
    }

    public function add(): void
    {
        $user = Helpers::requireUser();
        $body = Helpers::body();
        $productId = Helpers::require($body, 'productId', 'positive_int');
        $quantity  = max(1, (int)($body['quantity'] ?? 1));

        $product = $this->loadProduct($productId);
        if ($quantity > $product['stock']) {
            Helpers::error('Insufficient stock for ' . $product['name'], 409);
        }

        $pdo = Database::pdo();
        // INSERT or update existing row.
        $stmt = $pdo->prepare(
            'INSERT INTO cart_items (user_id, product_id, quantity)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE quantity = LEAST(quantity + VALUES(quantity), ?)'
        );
        $stmt->execute([$user['id'], $productId, $quantity, $product['stock']]);

        Helpers::json($this->cartFor((int)$user['id']), 201);
    }

    public function update(int $productId): void
    {
        $user = Helpers::requireUser();
        $body = Helpers::body();
        $quantity = isset($body['quantity']) ? (int)$body['quantity'] : -1;
        if ($quantity < 0) Helpers::error('quantity must be >= 0', 422);

        $pdo = Database::pdo();
        if ($quantity === 0) {
            $del = $pdo->prepare('DELETE FROM cart_items WHERE user_id = ? AND product_id = ?');
            $del->execute([$user['id'], $productId]);
            Helpers::json($this->cartFor((int)$user['id']));
        }

        $product = $this->loadProduct($productId);
        if ($quantity > $product['stock']) {
            Helpers::error('Insufficient stock for ' . $product['name'], 409);
        }

        $stmt = $pdo->prepare(
            'INSERT INTO cart_items (user_id, product_id, quantity)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE quantity = VALUES(quantity)'
        );
        $stmt->execute([$user['id'], $productId, $quantity]);
        Helpers::json($this->cartFor((int)$user['id']));
    }

    public function remove(int $productId): void
    {
        $user = Helpers::requireUser();
        $stmt = Database::pdo()->prepare('DELETE FROM cart_items WHERE user_id = ? AND product_id = ?');
        $stmt->execute([$user['id'], $productId]);
        Helpers::json($this->cartFor((int)$user['id']));
    }

    public function clear(): void
    {
        $user = Helpers::requireUser();
        $stmt = Database::pdo()->prepare('DELETE FROM cart_items WHERE user_id = ?');
        $stmt->execute([$user['id']]);
        Helpers::json($this->cartFor((int)$user['id']));
    }

    /** Returns the cart DTO ({ items, subtotal, totalQuantity }). */
    public function cartFor(int $userId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT ci.product_id, ci.quantity,
                    p.name, p.image_url, p.price, p.stock
             FROM cart_items ci
             JOIN products p ON p.id = ci.product_id
             WHERE ci.user_id = ?
             ORDER BY ci.product_id'
        );
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll();

        $items = [];
        $subtotal = 0.0;
        $qty = 0;
        foreach ($rows as $r) {
            $line = (float)$r['price'] * (int)$r['quantity'];
            $subtotal += $line;
            $qty += (int)$r['quantity'];
            $items[] = [
                'productId'   => (int)$r['product_id'],
                'productName' => $r['name'],
                'imageUrl'    => $r['image_url'],
                'unitPrice'   => (float)$r['price'],
                'quantity'    => (int)$r['quantity'],
                'stock'       => (int)$r['stock'],
                'lineTotal'   => $line,
            ];
        }
        return ['items' => $items, 'subtotal' => $subtotal, 'totalQuantity' => $qty];
    }

    private function loadProduct(int $id): array
    {
        $stmt = Database::pdo()->prepare('SELECT id, name, price, stock FROM products WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) Helpers::error('Product not found', 404);
        return $row;
    }
}
