<?php
// Minimal HS256 JWT implementation (no external dependencies).
// Compatible with the contract used by the previous .NET backend:
// the "sub" claim carries the user id and "role" is included for authorisation.

namespace MyStore;

use RuntimeException;

class JWT
{
    public static function encode(array $claims): string
    {
        $cfg = $GLOBALS['MYSTORE_CONFIG']['jwt'];
        $now = time();
        $payload = array_merge([
            'iss' => $cfg['issuer'],
            'aud' => $cfg['audience'],
            'iat' => $now,
            'exp' => $now + ($cfg['ttl_minutes'] * 60),
        ], $claims);

        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $segments = [
            self::b64url(json_encode($header, JSON_UNESCAPED_SLASHES)),
            self::b64url(json_encode($payload, JSON_UNESCAPED_SLASHES)),
        ];
        $signature = hash_hmac('sha256', implode('.', $segments), $cfg['secret'], true);
        $segments[] = self::b64url($signature);
        return implode('.', $segments);
    }

    /** Returns the payload array, or throws on invalid/expired tokens. */
    public static function decode(string $token): array
    {
        $cfg = $GLOBALS['MYSTORE_CONFIG']['jwt'];
        $parts = explode('.', $token);
        if (count($parts) !== 3) throw new RuntimeException('Malformed token');

        [$h, $p, $s] = $parts;
        $expected = self::b64url(hash_hmac('sha256', "$h.$p", $cfg['secret'], true));
        if (!hash_equals($expected, $s)) throw new RuntimeException('Invalid signature');

        $payload = json_decode(self::b64urlDecode($p), true);
        if (!is_array($payload)) throw new RuntimeException('Invalid payload');
        if (isset($payload['exp']) && time() >= $payload['exp']) {
            throw new RuntimeException('Token expired');
        }
        if (isset($payload['iss']) && $payload['iss'] !== $cfg['issuer']) {
            throw new RuntimeException('Wrong issuer');
        }
        if (isset($payload['aud']) && $payload['aud'] !== $cfg['audience']) {
            throw new RuntimeException('Wrong audience');
        }
        return $payload;
    }

    private static function b64url(string $bin): string
    {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }

    private static function b64urlDecode(string $s): string
    {
        $pad = strlen($s) % 4;
        if ($pad) $s .= str_repeat('=', 4 - $pad);
        return base64_decode(strtr($s, '-_', '+/'));
    }
}
