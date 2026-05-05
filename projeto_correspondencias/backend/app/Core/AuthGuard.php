<?php

namespace App\Core;

class AuthGuard
{
    public static function requireAdminSession(): void
    {
        if (self::hasAdminSession()) {
            return;
        }

        self::jsonUnauthorized('Sessao admin obrigatoria.');
    }

    public static function requireAdminSessionOrMobileJwt(): void
    {
        if (self::hasAdminSession()) {
            return;
        }

        $payload = self::mobilePayload();
        if ($payload !== null) {
            return;
        }

        self::jsonUnauthorized('JWT mobile obrigatorio.');
    }

    public static function hasAdminSession(): bool
    {
        return isset($_SESSION['admin_id']);
    }

    private static function mobilePayload(): ?array
    {
        $token = self::bearerToken();
        $payload = JwtHandler::decode($token);

        if (($payload['type'] ?? '') !== 'mobile') {
            return null;
        }

        if (!self::mobileUserStillAllowed($payload)) {
            return null;
        }

        return $payload;
    }

    private static function mobileUserStillAllowed(array $payload): bool
    {
        $deviceId = (int) ($payload['did'] ?? 0);
        $deviceHash = (string) ($payload['dh'] ?? '');

        if ($deviceId <= 0 || $deviceHash === '') {
            return false;
        }

        $stmt = Database::connection()->prepare(
            "SELECT COUNT(*)
             FROM mobile_users mu
             INNER JOIN mobile_user_devices d ON d.mobile_user_id = mu.id
             WHERE mu.id = :id
               AND mu.username = :username
               AND mu.active = 1
               AND mu.deleted_at IS NULL
               AND mu.expires_at >= NOW()
               AND d.id = :device_id
               AND d.device_hash = :device_hash
               AND d.status = 'approved'"
        );
        $stmt->execute([
            'id' => (int) ($payload['uid'] ?? 0),
            'username' => (string) ($payload['sub'] ?? ''),
            'device_id' => $deviceId,
            'device_hash' => $deviceHash,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private static function bearerToken(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        if ($header === '' && function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            $header = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        }

        if (!preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            return null;
        }

        return trim($matches[1]);
    }

    private static function jsonUnauthorized(string $message): void
    {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['error' => $message], JSON_UNESCAPED_SLASHES);
        exit;
    }
}
