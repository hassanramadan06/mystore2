<?php
// Search/list/get + admin CRUD.
// Search supports: search (text), category (slug), minPrice, maxPrice,
// sort (price_asc | price_desc | newest | name), featured, page, pageSize.

namespace MyStore\Controllers;

use MyStore\Database;
use MyStore\Helpers;

class ProductsController
{
    public function search(): void
    {
        $q = $_GET;
        $where = [];
        $params = [];

        if (!empty($q['search'])) {
            $where[] = '(p.name LIKE ? OR p.description LIKE ? OR p.brand LIKE ?)';
            $like = '%' . $q['search'] . '%';
            $params[] = $like; $params[] = $like; $params[] = $like;
        }
        if (!empty($q['category'])) {
            $where[] = 'c.slug = ?';
            $params[] = $q['category'];
        }
        if (isset($q['minPrice']) && $q['minPrice'] !== '') {
            $where[] = 'p.price >= ?';
            $params[] = (float)$q['minPrice'];
        }
        if (isset($q['maxPrice']) && $q['maxPrice'] !== '') {
            $where[] = 'p.price <= ?';
            $params[] = (float)$q['maxPrice'];
        }
        if (isset($q['featured']) && in_array(strtolower($q['featured']), ['true', '1'], true)) {
            $where[] = 'p.is_featured = 1';
        }

        $orderBy = match (strtolower($q['sort'] ?? '')) {
            'price_asc'  => 'p.price ASC',
            'price_desc' => 'p.price DESC',
            'name'       => 'p.name ASC',
            'newest'     => 'p.created_at DESC',
            default      => 'p.id ASC',
        };

        $page     = max(1, (int)($q['page']     ?? 1));
        $pageSize = max(1, min(100, (int)($q['pageSize'] ?? 20)));
        $offset   = ($page - 1) * $pageSize;

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $pdo = Database::pdo();

        // Count
        $countSql = "SELECT COUNT(*) FROM products p JOIN categories c ON c.id = p.category_id $whereSql";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        // Page
        $sql = "SELECT p.*, c.name AS category_name, c.slug AS category_slug
                FROM products p JOIN categories c ON c.id = p.category_id
                $whereSql
                ORDER BY $orderBy
                LIMIT $pageSize OFFSET $offset";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $items = array_map([$this, 'toDto'], $stmt->fetchAll());

        Helpers::json([
            'items'    => $items,
            'total'    => $total,
            'page'     => $page,
            'pageSize' => $pageSize,
        ]);
    }

    public function featured(): void
    {
        $take = max(1, min(50, (int)($_GET['take'] ?? 8)));
        $stmt = Database::pdo()->prepare(
            'SELECT p.*, c.name AS category_name, c.slug AS category_slug
             FROM products p JOIN categories c ON c.id = p.category_id
             WHERE p.is_featured = 1
             ORDER BY p.id ASC
             LIMIT ' . $take
        );
        $stmt->execute();
        Helpers::json(array_map([$this, 'toDto'], $stmt->fetchAll()));
    }

    public function get(int $id): void
    {
        $stmt = Database::pdo()->prepare(
            'SELECT p.*, c.name AS category_name, c.slug AS category_slug
             FROM products p JOIN categories c ON c.id = p.category_id
             WHERE p.id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) Helpers::error('Product not found', 404);
        Helpers::json($this->toDto($row));
    }

    public function create(): void
{
    Helpers::requireAdmin();
     

    // بيانات عادية من FormData
    $name = $_POST['name'] ?? null;
    $description = $_POST['description'] ?? '';
    $price = (float)($_POST['price'] ?? 0);
    $stock = (int)($_POST['stock'] ?? 0);
    $brand = $_POST['brand'] ?? null;
    $isFeatured = !empty($_POST['isFeatured']) ? 1 : 0;
    $categoryId = (int)($_POST['categoryId'] ?? 0);

    if (!$name) Helpers::error('name required', 422);
    if (!$categoryId) Helpers::error('categoryId required', 422);

    /// 🟢 رفع الصورة
$imageUrl = null;

if (!empty($_FILES['image']['name'])) {

    $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/mystore/uploads/products/';

    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $fileName = time() . '_' . basename($_FILES['image']['name']);
    $targetPath = $uploadDir . $fileName;

    if (!move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
        die("UPLOAD FAILED");
    }

    $imageUrl = "/mystore/uploads/products/" . $fileName;
}

    $pdo = Database::pdo();
    $stmt = $pdo->prepare(
        'INSERT INTO products 
        (name, description, price, stock, image_url, brand, is_featured, category_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );

    $stmt->execute([
        $name,
        $description,
        $price,
        $stock,
        $imageUrl,
        $brand,
        $isFeatured,
        $categoryId
    ]);

    $id = (int)$pdo->lastInsertId();
    $this->get($id);
}

    public function update(int $id): void
{
    Helpers::requireAdmin();
    $method = $_POST['_method'] ?? $_SERVER['REQUEST_METHOD'];

  if ($method !== 'PUT') {
    Helpers::error('Invalid method', 405);
  }

    $name = $_POST['name'] ?? null;
    $description = $_POST['description'] ?? '';
    $price = (float)($_POST['price'] ?? 0);
    $stock = (int)($_POST['stock'] ?? 0);
    $brand = $_POST['brand'] ?? null;
    $isFeatured = !empty($_POST['isFeatured']) ? 1 : 0;
    $categoryId = (int)($_POST['categoryId'] ?? 0);

    if (!$name) Helpers::error('name required', 422);

    // image
    $imageUrl = null;

if (!empty($_FILES['image']['name'])) {

    $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/mystore/uploads/products/';

    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $fileName = time() . '_' . basename($_FILES['image']['name']);
    $targetPath = $uploadDir . $fileName;

    if (!move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
        die("UPLOAD FAILED");
    }

    $imageUrl = "/mystore/uploads/products/" . $fileName;
}

    $stmt = Database::pdo()->prepare(
        'UPDATE products
         SET name=?, description=?, price=?, stock=?,
             image_url=?, brand=?, is_featured=?, category_id=?
         WHERE id=?'
    );

    $stmt->execute([
        $name,
        $description,
        $price,
        $stock,
        $imageUrl,
        $brand,
        $isFeatured,
        $categoryId,
        $id
    ]);

    $this->get($id);
}

    public function delete(int $id): void
    {
        Helpers::requireAdmin();
        $stmt = Database::pdo()->prepare('DELETE FROM products WHERE id = ?');
        try {
            $stmt->execute([$id]);
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                Helpers::error('Product is referenced by orders and cannot be deleted', 409);
            }
            throw $e;
        }
        if ($stmt->rowCount() === 0) Helpers::error('Product not found', 404);
        Helpers::json(['ok' => true], 204);
    }

    private function validateProduct(array $body): array
    {
        $name        = Helpers::require($body, 'name', 'string', 150);
        $description = (string)($body['description'] ?? '');
        $price       = isset($body['price']) ? (float)$body['price'] : -1;
        $stock       = isset($body['stock']) ? (int)$body['stock'] : -1;
        $imageUrl    = (string)($body['imageUrl'] ?? '');
        $brand       = $body['brand'] ?? null;
        $isFeatured  = !empty($body['isFeatured']) ? 1 : 0;
        $categoryId  = isset($body['categoryId']) ? (int)$body['categoryId'] : 0;

        if ($price < 0)   Helpers::error('price must be >= 0', 422);
        if ($stock < 0)   Helpers::error('stock must be >= 0', 422);
        if (!$categoryId) Helpers::error('categoryId is required', 422);

        return [
            'name'        => $name,
            'description' => $description,
            'price'       => $price,
            'stock'       => $stock,
            'image_url'   => $imageUrl,
            'brand'       => $brand,
            'is_featured' => $isFeatured,
            'category_id' => $categoryId,
        ];
    }

    private function toDto(array $row): array
    {
        return [
            'id'           => (int)$row['id'],
            'name'         => $row['name'],
            'description'  => $row['description'],
            'price'        => (float)$row['price'],
            'stock'        => (int)$row['stock'],
            'imageUrl' => $row['image_url'],
            'brand'        => $row['brand'],
            'isFeatured'   => (bool)$row['is_featured'],
            'categoryId'   => (int)$row['category_id'],
            'categoryName' => $row['category_name'] ?? '',
            'categorySlug' => $row['category_slug'] ?? '',
        ];
    }
}
