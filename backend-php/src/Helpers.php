<?php
// Request/response helpers shared across controllers.

namespace MyStore;

use Throwable;

class Helpers
{
    /** Send a JSON response and exit. */
    public static function json($data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /** Send an error response. */
    public static function error(string $message, int $status = 400): void
    {
        self::json(['error' => $message], $status);
    }

    /** Decode the JSON request body into an associative array. */
    public static function body(): array
    {
        $raw = file_get_contents('php://input');
        if (!$raw) return [];
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            self::error('Invalid JSON body', 400);
        }
        return $data;
    }

    /** Read a value out of $arr, validating type and bounds. Throws on failure. */
    public static function require(array $arr, string $key, string $type = 'string', int $maxLen = 0)
    {
        if (!array_key_exists($key, $arr)) self::error("Missing field: $key", 422);
        $v = $arr[$key];
        if ($type === 'string') {
            if (!is_string($v) || trim($v) === '') self::error("Field $key must be a non-empty string", 422);
            if ($maxLen > 0 && mb_strlen($v) > $maxLen) self::error("Field $key too long", 422);
            return trim($v);
        }
        if ($type === 'int') {
            if (!is_int($v) && !(is_string($v) && ctype_digit($v))) self::error("Field $key must be an integer", 422);
            return (int)$v;
        }
        if ($type === 'positive_int') {
            $n = is_int($v) ? $v : (int)$v;
            if ($n <= 0) self::error("Field $key must be a positive integer", 422);
            return $n;
        }
        return $v;
    }

    /** Set CORS headers based on the request Origin and the configured allow-list. */
    public static function applyCors(): void
    {
        $allowed = $GLOBALS['MYSTORE_CONFIG']['cors_origins'] ?? [];
        $origin  = $_SERVER['HTTP_ORIGIN'] ?? '';

        if ($origin && in_array($origin, $allowed, true)) {
            header("Access-Control-Allow-Origin: $origin");
            header('Vary: Origin');
            header('Access-Control-Allow-Credentials: true');
        }
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        header('Access-Control-Max-Age: 600');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }

    /** Returns ['id' => int, 'role' => string] if a valid Bearer JWT is present, else null. */
    public static function currentUser(): ?array
    {
        $token = self::extractBearer();
        if (!$token) return null;
        try {
            $payload = JWT::decode($token);
        } catch (Throwable $e) {
            return null;
        }
        return [
            'id'       => (int)($payload['sub'] ?? 0),
            'fullName' => $payload['name']  ?? '',
            'email'    => $payload['email'] ?? '',
            'role'     => $payload['role']  ?? 'Customer',
        ];
    }

    /** Same as currentUser() but sends 401 on failure. */
    public static function requireUser(): array
    {
        $user = self::currentUser();
        if (!$user) self::error('Unauthorised', 401);
        return $user;
    }

    public static function requireAdmin(): array
    {
        $user = self::requireUser();
        if ($user['role'] !== 'Admin') self::error('Forbidden', 403);
        return $user;
    }

    private static function extractBearer(): ?string
    {
        // Apache may put the header in HTTP_AUTHORIZATION or REDIRECT_HTTP_AUTHORIZATION.
        $auth = $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? '';
        if (!$auth && function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            $auth = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        }
        if (preg_match('/^Bearer\s+(.+)$/i', $auth, $m)) {
            return trim($m[1]);
        }
        return null;
    }
}
