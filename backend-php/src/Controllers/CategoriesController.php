<?php
// Public list endpoint + admin CRUD. Mirrors the .NET CategoriesController.

namespace MyStore\Controllers;

use MyStore\Database;
use MyStore\Helpers;

class CategoriesController
{
    public function list(): void
    {
        $rows = Database::pdo()->query(
            'SELECT c.id, c.name, c.slug, c.description,
                    (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS product_count
             FROM categories c
             ORDER BY c.id'
        )->fetchAll();
        Helpers::json(array_map([$this, 'toDto'], $rows));
    }

    public function get(int $id): void
    {
        $stmt = Database::pdo()->prepare(
            'SELECT c.id, c.name, c.slug, c.description,
                    (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS product_count
             FROM categories c WHERE c.id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) Helpers::error('Category not found', 404);
        Helpers::json($this->toDto($row));
    }

    public function create(): void
    {
        Helpers::requireAdmin();
        $body = Helpers::body();
        $name = Helpers::require($body, 'name', 'string', 80);
        $slug = Helpers::require($body, 'slug', 'string', 80);
        $description = $body['description'] ?? null;

        $pdo = Database::pdo();
        $stmt = $pdo->prepare('INSERT INTO categories (name, slug, description) VALUES (?, ?, ?)');
        try {
            $stmt->execute([$name, $slug, $description]);
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') Helpers::error('Slug already exists', 409);
            throw $e;
        }
        $id = (int)$pdo->lastInsertId();
        $this->get($id);
    }

    public function update(int $id): void
    {
        Helpers::requireAdmin();
        $body = Helpers::body();
        $name = Helpers::require($body, 'name', 'string', 80);
        $slug = Helpers::require($body, 'slug', 'string', 80);
        $description = $body['description'] ?? null;

        $stmt = Database::pdo()->prepare(
            'UPDATE categories SET name = ?, slug = ?, description = ? WHERE id = ?'
        );
        $stmt->execute([$name, $slug, $description, $id]);
        if ($stmt->rowCount() === 0) Helpers::error('Category not found', 404);
        $this->get($id);
    }

    public function delete(int $id): void
    {
        Helpers::requireAdmin();
        $stmt = Database::pdo()->prepare('DELETE FROM categories WHERE id = ?');
        try {
            $stmt->execute([$id]);
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') Helpers::error('Category has products and cannot be deleted', 409);
            throw $e;
        }
        if ($stmt->rowCount() === 0) Helpers::error('Category not found', 404);
        Helpers::json(['ok' => true], 204);
    }

    private function toDto(array $row): array
    {
        return [
            'id'           => (int)$row['id'],
            'name'         => $row['name'],
            'slug'         => $row['slug'],
            'description'  => $row['description'],
            'productCount' => (int)$row['product_count'],
        ];
    }
}
