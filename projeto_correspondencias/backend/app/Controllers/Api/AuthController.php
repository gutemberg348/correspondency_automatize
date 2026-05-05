<?php

namespace App\Controllers\Api;

use App\Core\Database;
use App\Core\JwtHandler;
use App\Models\MobileDevice;
use App\Models\MobileUser;
use InvalidArgumentException;

class AuthController
{
    public function mobileLogin(): void
    {
        $data = json_decode(file_get_contents('php://input') ?: '[]', true) ?: [];

        $username = (string) ($data['username'] ?? '');
        $password = (string) ($data['password'] ?? '');
        $deviceData = $this->deviceData($data);

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            "SELECT id, name, username, password_hash, validity_amount, validity_unit,
                    expires_at,
                    DATE_FORMAT(expires_at, '%Y-%m-%dT%H:%i:%s') AS expires_at_iso,
                    active,
                    DATE_FORMAT(created_at, '%Y-%m-%dT%H:%i:%s') AS created_at,
                    CASE WHEN expires_at < NOW() THEN 1 ELSE 0 END AS expired
             FROM mobile_users
             WHERE username = :username
               AND deleted_at IS NULL
             LIMIT 1"
        );
        $stmt->execute(['username' => strtolower(trim($username))]);
        $user = $stmt->fetch();
        $mobileUsers = new MobileUser();

        if (
            !$user
            || !(bool) $user['active']
            || (bool) $user['expired']
            || !$mobileUsers->passwordMatches($password, (string) $user['password_hash'])
        ) {
            $this->json(['error' => 'Login invalido ou acesso vencido.', 'code' => 'AUTH_INVALID'], 401);
            return;
        }

        if (empty($deviceData['install_id'])) {
            $this->json(['error' => 'Dispositivo nao identificado.', 'code' => 'DEVICE_MISSING'], 400);
            return;
        }

        try {
            $deviceVerification = (new MobileDevice())->verifyForLogin((int) $user['id'], $deviceData);
        } catch (InvalidArgumentException $exception) {
            $this->json(['error' => $exception->getMessage(), 'code' => 'DEVICE_MISSING'], 400);
            return;
        }

        if (!$deviceVerification['allowed']) {
            $this->json([
                'error' => $deviceVerification['status'] === 'blocked'
                    ? 'Celular bloqueado para este login.'
                    : 'Celular aguardando liberacao do administrador.',
                'code' => $deviceVerification['status'] === 'blocked' ? 'DEVICE_BLOCKED' : 'DEVICE_PENDING',
                'device' => $deviceVerification['device'],
            ], 403);
            return;
        }

        $update = $pdo->prepare('UPDATE mobile_users SET last_login_at = NOW() WHERE id = :id');
        $update->execute(['id' => $user['id']]);

        $user['expires_at'] = $user['expires_at_iso'] ?: $user['expires_at'];
        $user['id'] = (int) $user['id'];
        $user['validity_amount'] = (int) $user['validity_amount'];
        $user['active'] = (bool) $user['active'];
        $user['expired'] = (bool) $user['expired'];
        unset($user['password_hash'], $user['expires_at_iso']);

        $this->json([
            'token' => JwtHandler::encode([
                'sub' => $user['username'],
                'uid' => $user['id'],
                'type' => 'mobile',
                'did' => (int) $deviceVerification['device']['id'],
                'dh' => $deviceVerification['device_hash'],
                'device_id' => $deviceVerification['device']['id'],
                'exp' => strtotime((string) $user['expires_at']) ?: (time() + 3600),
            ]),
            'user' => $user,
            'device' => $deviceVerification['device'],
        ]);
    }

    private function deviceData(array $data): array
    {
        $device = $data['device'] ?? null;

        if (is_array($device)) {
            return $device;
        }

        return $data;
    }

    private function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_UNESCAPED_SLASHES);
    }
}
