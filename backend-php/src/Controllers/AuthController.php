<?php
// Handles register/login. Issues HS256 JWTs and returns the same shape as the
// previous .NET API ({ token, user: { id, fullName, email, role } }).

namespace MyStore\Controllers;

use MyStore\Database;
use MyStore\Helpers;
use MyStore\JWT;

class AuthController
{
    public function register(): void
    {
        $body = Helpers::body();
        $fullName = Helpers::require($body, 'fullName', 'string', 100);
        $email    = strtolower(Helpers::require($body, 'email', 'string', 256));
        $password = Helpers::require($body, 'password', 'string', 100);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) Helpers::error('Invalid email', 422);
        if (strlen($password) < 6) Helpers::error('Password must be at least 6 characters', 422);

        $pdo = Database::pdo();
        $exists = $pdo->prepare('SELECT 1 FROM users WHERE email = ?');
        $exists->execute([$email]);
        if ($exists->fetchColumn()) Helpers::error('Email already registered', 409);

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare(
            'INSERT INTO users (full_name, email, password_hash, role) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$fullName, $email, $hash, 'Customer']);
        $id = (int)$pdo->lastInsertId();

        $user = ['id' => $id, 'fullName' => $fullName, 'email' => $email, 'role' => 'Customer'];
        Helpers::json($this->authResponse($user), 201);
    }

    public function login(): void
    {
        $body = Helpers::body();
        $email    = strtolower(Helpers::require($body, 'email', 'string', 256));
        $password = Helpers::require($body, 'password', 'string', 100);

        $stmt = Database::pdo()->prepare(
            'SELECT id, full_name, email, role, password_hash FROM users WHERE email = ?'
        );
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        if (!$row || !password_verify($password, $row['password_hash'])) {
            Helpers::error('Invalid credentials', 401);
        }

        $user = [
            'id'       => (int)$row['id'],
            'fullName' => $row['full_name'],
            'email'    => $row['email'],
            'role'     => $row['role'],
        ];
        Helpers::json($this->authResponse($user));
    }

    public function me(): void
    {
        $user = Helpers::requireUser();
        $stmt = Database::pdo()->prepare('SELECT id, full_name, email, role FROM users WHERE id = ?');
        $stmt->execute([$user['id']]);
        $row = $stmt->fetch();
        if (!$row) Helpers::error('User not found', 404);
        Helpers::json([
            'id'       => (int)$row['id'],
            'fullName' => $row['full_name'],
            'email'    => $row['email'],
            'role'     => $row['role'],
        ]);
    }

    private function authResponse(array $user): array
    {
        $token = JWT::encode([
            'sub'   => $user['id'],
            'name'  => $user['fullName'],
            'email' => $user['email'],
            'role'  => $user['role'],
        ]);
        return ['token' => $token, 'user' => $user];
    }
}
