<?php

namespace App\Models;

use App\Core\Database;
use PDO;
use Throwable;

class Package
{
    public function all(): array
    {
        $stmt = Database::connection()->query($this->baseSelect() . ' ORDER BY p.received_at DESC');

        return $stmt->fetchAll();
    }

    public function create(array $data): array
    {
        $unit = trim((string) ($data['unit'] ?? ''));
        $unitShort = trim((string) ($data['unit_short'] ?? ''));
        $identification = trim((string) ($data['identification'] ?? ''));

        if ($unitShort === '') {
            $unitShort = preg_replace('/^Unidade\s+/i', '', $unit);
            $unitShort = preg_split('/[,\s-]+/', (string) $unitShort)[0] ?: $unit;
        }

        $pdo = Database::connection();

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                'INSERT INTO packages (unit, unit_short, identification, status, received_at)
                 VALUES (:unit, :unit_short, :identification, "pendente", NOW())'
            );
            $stmt->execute([
                'unit' => $unit,
                'unit_short' => $unitShort,
                'identification' => $identification,
            ]);

            $id = (int) $pdo->lastInsertId();
            $this->createEvent($pdo, $id, 'created', 'system', null, 'Correspondencia cadastrada');

            $pdo->commit();

            return $this->find($id);
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    public function deliver(int|string $id, array $data): ?array
    {
        $pdo = Database::connection();
        $packageId = (int) $id;
        $receiver = trim((string) ($data['receiver'] ?? ''));
        $signature = (string) ($data['signature'] ?? '');

        try {
            $pdo->beginTransaction();

            $exists = $pdo->prepare('SELECT id FROM packages WHERE id = :id AND deleted_at IS NULL LIMIT 1');
            $exists->execute(['id' => $packageId]);

            if (!$exists->fetch()) {
                $pdo->rollBack();
                return null;
            }

            $delivery = $pdo->prepare(
                'INSERT INTO package_deliveries (package_id, receiver, signature_data, delivered_at)
                 VALUES (:package_id, :receiver, :signature_data, NOW())
                 ON DUPLICATE KEY UPDATE
                    receiver = VALUES(receiver),
                    signature_data = VALUES(signature_data),
                    signature_path = NULL,
                    delivered_at = VALUES(delivered_at),
                    updated_at = CURRENT_TIMESTAMP'
            );
            $delivery->execute([
                'package_id' => $packageId,
                'receiver' => $receiver,
                'signature_data' => $signature,
            ]);

            $update = $pdo->prepare('UPDATE packages SET status = "entregue" WHERE id = :id');
            $update->execute(['id' => $packageId]);

            $this->createEvent($pdo, $packageId, 'delivered', 'system', null, 'Correspondencia entregue');

            $pdo->commit();

            return $this->find($packageId);
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    private function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare($this->baseSelect() . ' AND p.id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
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
                    p.status,
                    DATE_FORMAT(p.received_at, '%Y-%m-%dT%H:%i:%s') AS received_at,
                    d.receiver,
                    COALESCE(d.signature_data, d.signature_path) AS signature,
                    DATE_FORMAT(d.delivered_at, '%Y-%m-%dT%H:%i:%s') AS delivered_at
                FROM packages p
                LEFT JOIN package_deliveries d ON d.package_id = p.id
                WHERE p.deleted_at IS NULL";
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
