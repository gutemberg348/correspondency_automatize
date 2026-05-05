<?php

namespace App\Models;

use App\Core\Database;
use InvalidArgumentException;
use PDO;

class MobileDevice
{
    public function all(): array
    {
        $stmt = Database::connection()->query(
            "SELECT d.id,
                    d.mobile_user_id,
                    mu.name AS user_name,
                    mu.username,
                    d.device_label,
                    d.platform,
                    d.model,
                    d.manufacturer,
                    d.app_version,
                    d.status,
                    DATE_FORMAT(d.last_login_at, '%Y-%m-%dT%H:%i:%s') AS last_login_at,
                    DATE_FORMAT(d.approved_at, '%Y-%m-%dT%H:%i:%s') AS approved_at,
                    DATE_FORMAT(d.created_at, '%Y-%m-%dT%H:%i:%s') AS created_at,
                    DATE_FORMAT(d.updated_at, '%Y-%m-%dT%H:%i:%s') AS updated_at
             FROM mobile_user_devices d
             INNER JOIN mobile_users mu ON mu.id = d.mobile_user_id
             WHERE mu.deleted_at IS NULL
             ORDER BY FIELD(d.status, 'pending', 'approved', 'blocked'), d.updated_at DESC"
        );

        return array_map([$this, 'normalizePublicDevice'], $stmt->fetchAll());
    }

    public function verifyForLogin(int $userId, array $data): array
    {
        $installId = trim((string) ($data['install_id'] ?? ''));

        if ($installId === '') {
            throw new InvalidArgumentException('Dispositivo nao identificado.');
        }

        $pdo = Database::connection();
        $hash = hash('sha256', $installId);
        $device = $this->findByHash($pdo, $userId, $hash);
        $payload = $this->devicePayload($data);

        if (!$device) {
            $device = $this->createDevice($pdo, $userId, $hash, $payload, 'pending');
        } else {
            $device = $this->updateSeenDevice($pdo, (int) $device['id'], $payload);
        }

        if ($device['status'] === 'approved') {
            $this->touchLogin((int) $device['id']);
            $device = $this->findPublic((int) $device['id']);

            return [
                'allowed' => true,
                'status' => 'approved',
                'device' => $device,
                'device_hash' => $hash,
            ];
        }

        return [
            'allowed' => false,
            'status' => $device['status'],
            'device' => $this->normalizePublicDevice($device),
            'device_hash' => $hash,
        ];
    }

    public function approve(int $id, int $adminId): ?array
    {
        $stmt = Database::connection()->prepare(
            "UPDATE mobile_user_devices
             SET status = 'approved',
                 approved_by_admin_id = :admin_id,
                 approved_at = NOW(),
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id"
        );
        $stmt->execute([
            'id' => $id,
            'admin_id' => $adminId ?: null,
        ]);

        return $this->findPublic($id);
    }

    public function block(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            "UPDATE mobile_user_devices
             SET status = 'blocked',
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id"
        );
        $stmt->execute(['id' => $id]);

        return $this->findPublic($id);
    }

    public function delete(int $id): bool
    {
        $stmt = Database::connection()->prepare('DELETE FROM mobile_user_devices WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }

    private function findByHash(PDO $pdo, int $userId, string $hash): ?array
    {
        $stmt = $pdo->prepare(
            "SELECT *
             FROM mobile_user_devices
             WHERE mobile_user_id = :user_id
               AND device_hash = :device_hash
             LIMIT 1"
        );
        $stmt->execute([
            'user_id' => $userId,
            'device_hash' => $hash,
        ]);

        $device = $stmt->fetch();

        return $device ?: null;
    }

    private function createDevice(PDO $pdo, int $userId, string $hash, array $payload, string $status): array
    {
        $stmt = $pdo->prepare(
            "INSERT INTO mobile_user_devices
                (mobile_user_id, device_hash, device_label, platform, model, manufacturer, app_version, status, approved_at)
             VALUES
                (:user_id, :device_hash, :device_label, :platform, :model, :manufacturer, :app_version, :status, :approved_at)"
        );
        $stmt->execute([
            'user_id' => $userId,
            'device_hash' => $hash,
            'device_label' => $payload['device_label'],
            'platform' => $payload['platform'],
            'model' => $payload['model'],
            'manufacturer' => $payload['manufacturer'],
            'app_version' => $payload['app_version'],
            'status' => $status,
            'approved_at' => $status === 'approved' ? date('Y-m-d H:i:s') : null,
        ]);

        return $this->findRaw((int) $pdo->lastInsertId()) ?: [];
    }

    private function updateSeenDevice(PDO $pdo, int $id, array $payload): array
    {
        $stmt = $pdo->prepare(
            "UPDATE mobile_user_devices
             SET device_label = :device_label,
                 platform = :platform,
                 model = :model,
                 manufacturer = :manufacturer,
                 app_version = :app_version,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id"
        );
        $stmt->execute([
            'id' => $id,
            'device_label' => $payload['device_label'],
            'platform' => $payload['platform'],
            'model' => $payload['model'],
            'manufacturer' => $payload['manufacturer'],
            'app_version' => $payload['app_version'],
        ]);

        return $this->findRaw($id) ?: [];
    }

    private function touchLogin(int $id): void
    {
        $stmt = Database::connection()->prepare(
            "UPDATE mobile_user_devices
             SET last_login_at = NOW(),
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id"
        );
        $stmt->execute(['id' => $id]);
    }

    private function findRaw(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM mobile_user_devices WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $device = $stmt->fetch();

        return $device ?: null;
    }

    private function findPublic(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            "SELECT d.id,
                    d.mobile_user_id,
                    mu.name AS user_name,
                    mu.username,
                    d.device_label,
                    d.platform,
                    d.model,
                    d.manufacturer,
                    d.app_version,
                    d.status,
                    DATE_FORMAT(d.last_login_at, '%Y-%m-%dT%H:%i:%s') AS last_login_at,
                    DATE_FORMAT(d.approved_at, '%Y-%m-%dT%H:%i:%s') AS approved_at,
                    DATE_FORMAT(d.created_at, '%Y-%m-%dT%H:%i:%s') AS created_at,
                    DATE_FORMAT(d.updated_at, '%Y-%m-%dT%H:%i:%s') AS updated_at
             FROM mobile_user_devices d
             INNER JOIN mobile_users mu ON mu.id = d.mobile_user_id
             WHERE d.id = :id
             LIMIT 1"
        );
        $stmt->execute(['id' => $id]);
        $device = $stmt->fetch();

        return $device ? $this->normalizePublicDevice($device) : null;
    }

    private function devicePayload(array $data): array
    {
        $platform = trim((string) ($data['platform'] ?? ''));
        $model = trim((string) ($data['model'] ?? ''));
        $manufacturer = trim((string) ($data['manufacturer'] ?? ''));
        $appVersion = trim((string) ($data['app_version'] ?? ''));
        $label = trim((string) ($data['device_label'] ?? ''));

        if ($label === '') {
            $parts = array_filter([$manufacturer, $model]);
            $label = $parts ? implode(' ', $parts) : ($platform ?: 'Dispositivo mobile');
        }

        return [
            'device_label' => substr($label, 0, 160),
            'platform' => substr($platform ?: 'desconhecido', 0, 80),
            'model' => substr($model, 0, 120),
            'manufacturer' => substr($manufacturer, 0, 120),
            'app_version' => substr($appVersion, 0, 40),
        ];
    }

    private function normalizePublicDevice(array $device): array
    {
        $device['id'] = (int) $device['id'];
        $device['mobile_user_id'] = (int) $device['mobile_user_id'];

        return $device;
    }
}
