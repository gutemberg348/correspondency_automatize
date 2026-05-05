<?php

namespace App\Core;

class JwtHandler
{
    public static function encode(array $payload): string
    {
        $config = require dirname(__DIR__, 2) . '/config.php';
        $header = self::base64Url(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $body = self::base64Url(json_encode($payload + ['iat' => time()]));
        $signature = hash_hmac('sha256', $header . '.' . $body, $config['jwt_secret'], true);

        return $header . '.' . $body . '.' . self::base64Url($signature);
    }

    public static function validate(?string $token): bool
    {
        return self::decode($token) !== null;
    }

    public static function decode(?string $token): ?array
    {
        if (!$token || substr_count($token, '.') !== 2) {
            return null;
        }

        $config = require dirname(__DIR__, 2) . '/config.php';
        [$header, $body, $signature] = explode('.', $token);
        $expected = self::base64Url(hash_hmac('sha256', $header . '.' . $body, $config['jwt_secret'], true));

        if (!hash_equals($expected, $signature)) {
            return null;
        }

        $payload = json_decode(self::base64UrlDecode($body), true);

        if (!is_array($payload)) {
            return null;
        }

        if (isset($payload['exp']) && (int) $payload['exp'] < time()) {
            return null;
        }

        return $payload;
    }

    private static function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $value): string
    {
        $value .= str_repeat('=', (4 - strlen($value) % 4) % 4);
        return base64_decode(strtr($value, '-_', '+/')) ?: '';
    }
}
