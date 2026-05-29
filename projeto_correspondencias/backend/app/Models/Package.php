<?php

namespace App\Models;

use App\Core\Database;
use InvalidArgumentException;
use PDO;
use Throwable;

class Package
{
    public function all(array $actor = []): array
    {
        [$scopeSql, $scopeParams] = $this->visibilityScope($actor);
        $stmt = Database::connection()->prepare($this->baseSelect() . $scopeSql . ' ORDER BY p.received_at DESC');
        $stmt->execute($scopeParams);

        return $stmt->fetchAll();
    }

    public function create(array $data, array $actor = []): array
    {
        $unit = trim((string) ($data['unit'] ?? ''));
        $unitShort = trim((string) ($data['unit_short'] ?? ''));
        $identification = trim((string) ($data['identification'] ?? ''));
        $photo = $this->photoData($data['photo'] ?? '');

        if ($unitShort === '') {
            $unitShort = preg_replace('/^Unidade\s+/i', '', $unit);
            $unitShort = preg_split('/[,\s-]+/', (string) $unitShort)[0] ?: $unit;
        }

        $pdo = Database::connection();

        try {
            $pdo->beginTransaction();

            $mobileUserId = $this->mobileUserId($actor);
            $adminId = $this->adminIdForNewPackage($pdo, $actor);

            $stmt = $pdo->prepare(
                'INSERT INTO packages
                    (unit, unit_short, identification, photo_data, status, received_at, created_by_admin_id, created_by_mobile_user_id)
                 VALUES
                    (:unit, :unit_short, :identification, :photo_data, "pendente", NOW(), :created_by_admin_id, :created_by_mobile_user_id)'
            );
            $stmt->execute([
                'unit' => $unit,
                'unit_short' => $unitShort,
                'identification' => $identification,
                'photo_data' => $photo,
                'created_by_admin_id' => $adminId,
                'created_by_mobile_user_id' => $mobileUserId,
            ]);

            $id = (int) $pdo->lastInsertId();
            $this->createEvent($pdo, $id, 'created', $this->actorType($actor), $this->actorId($actor), 'Correspondencia cadastrada');

            $pdo->commit();

            return $this->find($id, $actor);
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    public function deliver(int|string $id, array $data, array $actor = []): ?array
    {
        $pdo = Database::connection();
        $packageId = (int) $id;
        $receiver = trim((string) ($data['receiver'] ?? ''));
        $signature = (string) ($data['signature'] ?? '');
        $mobileUserId = $this->mobileUserId($actor);
        [$scopeSql, $scopeParams] = $this->visibilityScope($actor);

        try {
            $pdo->beginTransaction();

            $exists = $pdo->prepare('SELECT p.id FROM packages p WHERE p.id = :id AND p.deleted_at IS NULL' . $scopeSql . ' LIMIT 1');
            $exists->execute(['id' => $packageId] + $scopeParams);

            if (!$exists->fetch()) {
                $pdo->rollBack();
                return null;
            }

            $delivery = $pdo->prepare(
                'INSERT INTO package_deliveries
                    (package_id, receiver, signature_data, delivered_by_mobile_user_id, delivered_at)
                 VALUES
                    (:package_id, :receiver, :signature_data, :delivered_by_mobile_user_id, NOW())
                 ON DUPLICATE KEY UPDATE
                    receiver = VALUES(receiver),
                    signature_data = VALUES(signature_data),
                    signature_path = NULL,
                    delivered_by_mobile_user_id = VALUES(delivered_by_mobile_user_id),
                    delivered_at = VALUES(delivered_at),
                    updated_at = CURRENT_TIMESTAMP'
            );
            $delivery->execute([
                'package_id' => $packageId,
                'receiver' => $receiver,
                'signature_data' => $signature,
                'delivered_by_mobile_user_id' => $mobileUserId,
            ]);

            $update = $pdo->prepare('UPDATE packages SET status = "entregue" WHERE id = :id');
            $update->execute(['id' => $packageId]);

            $this->createEvent($pdo, $packageId, 'delivered', $this->actorType($actor), $this->actorId($actor), 'Correspondencia entregue');

            $pdo->commit();

            return $this->find($packageId, $actor);
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    private function find(int $id, array $actor = []): ?array
    {
        [$scopeSql, $scopeParams] = $this->visibilityScope($actor);
        $stmt = Database::connection()->prepare($this->baseSelect() . ' AND p.id = :id' . $scopeSql . ' LIMIT 1');
        $stmt->execute(['id' => $id] + $scopeParams);
        $package = $stmt->fetch();

        return $package ?: null;
    }

    private function baseSelect(): string
    {
        return "SELECT
                    p.id,
                    p.unit,
                    p.unit_short,
                    p.identification,
                    p.photo_data AS photo,
                    p.status,
                    DATE_FORMAT(p.received_at, '%Y-%m-%dT%H:%i:%s') AS received_at,
                    d.receiver,
                    COALESCE(d.signature_data, d.signature_path) AS signature,
                    DATE_FORMAT(d.delivered_at, '%Y-%m-%dT%H:%i:%s') AS delivered_at
                FROM packages p
                LEFT JOIN package_deliveries d ON d.package_id = p.id
                WHERE p.deleted_at IS NULL";
    }

    private function photoData($value): ?string
    {
        $photo = trim((string) $value);

        if ($photo === '') {
            return null;
        }

        if (strlen($photo) > 6000000) {
            throw new InvalidArgumentException('Foto muito grande. Tire uma foto menor e tente novamente.');
        }

        if (!preg_match('#^data:image/(png|jpe?g|webp);base64,#i', $photo)) {
            throw new InvalidArgumentException('Foto invalida.');
        }

        return $photo;
    }

    private function visibilityScope(array $actor): array
    {
        if (($actor['type'] ?? '') === 'mobile_user') {
            return [' AND p.created_by_mobile_user_id = :scope_mobile_user_id', [
                'scope_mobile_user_id' => (int) ($actor['id'] ?? 0),
            ]];
        }

        return ['', []];
    }

    private function mobileUserId(array $actor): ?int
    {
        if (($actor['type'] ?? '') !== 'mobile_user') {
            return null;
        }

        $id = (int) ($actor['id'] ?? 0);

        return $id > 0 ? $id : null;
    }

    private function adminIdForNewPackage(PDO $pdo, array $actor): ?int
    {
        if (($actor['type'] ?? '') === 'admin') {
            $id = (int) ($actor['id'] ?? 0);
            return $id > 0 ? $id : null;
        }

        $mobileUserId = $this->mobileUserId($actor);
        if ($mobileUserId === null) {
            return null;
        }

        $stmt = $pdo->prepare('SELECT created_by_admin_id FROM mobile_users WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(['id' => $mobileUserId]);
        $adminId = $stmt->fetchColumn();

        return $adminId ? (int) $adminId : null;
    }

    private function actorType(array $actor): string
    {
        return (($actor['type'] ?? '') === 'admin') ? 'admin' : ((($actor['type'] ?? '') === 'mobile_user') ? 'mobile_user' : 'system');
    }

    private function actorId(array $actor): ?int
    {
        $id = (int) ($actor['id'] ?? 0);

        return $id > 0 ? $id : null;
    }

    private function createEvent(PDO $pdo, int $packageId, string $type, string $actorType, ?int $actorId, string $notes): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO package_events (package_id, event_type, actor_type, actor_id, notes)
             VALUES (:package_id, :event_type, :actor_type, :actor_id, :notes)'
        );
        $stmt->execute([
            'package_id' => $packageId,
            'event_type' => $type,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'notes' => $notes,
        ]);
    }
}
