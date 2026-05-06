<?php

namespace App\Models;

use App\Core\Database;
use DateTimeImmutable;

class MobileUser
{
    public function all(): array
    {
        $stmt = Database::connection()->query(
            "SELECT id, name, username, password_hash AS password, validity_amount, validity_unit,
                    DATE_FORMAT(expires_at, '%Y-%m-%dT%H:%i:%s') AS expires_at,
                    active,
                    DATE_FORMAT(created_at, '%Y-%m-%dT%H:%i:%s') AS created_at,
                    CASE WHEN expires_at < NOW() THEN 1 ELSE 0 END AS expired
             FROM mobile_users
             WHERE deleted_at IS NULL
             ORDER BY created_at DESC"
        );

        return array_map([$this, 'normalizePublicUser'], $stmt->fetchAll());
    }

    public function create(array $data, ?int $adminId = null): array
    {
        $amount = max(1, (int) ($data['validity_amount'] ?? 1));
        $unit = ($data['validity_unit'] ?? 'months') === 'days' ? 'days' : 'months';
        $password = trim((string) ($data['password'] ?? ''));
        $username = strtolower(trim((string) ($data['username'] ?? '')));
        $name = trim((string) ($data['name'] ?? $username));

        if ($username === '' || $password === '') {
            throw new \InvalidArgumentException('Login e senha sao obrigatorios.');
        }

        if ($this->usernameExists($username)) {
            throw new \InvalidArgumentException('Login ja cadastrado.');
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO mobile_users
                (name, username, password_hash, validity_amount, validity_unit, expires_at, active, created_by_admin_id)
             VALUES
                (:name, :username, :password_hash, :validity_amount, :validity_unit, :expires_at, 1, :created_by_admin_id)'
        );
        $stmt->execute([
            'name' => $name !== '' ? $name : $username,
            'username' => $username,
            'password_hash' => $password,
            'validity_amount' => $amount,
            'validity_unit' => $unit,
            'expires_at' => $this->expiresAt($amount, $unit),
            'created_by_admin_id' => $adminId && $adminId > 0 ? $adminId : null,
        ]);

        return $this->findPublic((int) $pdo->lastInsertId());
    }

    public function attempt(string $username, string $password): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            "SELECT id, name, username, password_hash, validity_amount, validity_unit,
                    DATE_FORMAT(expires_at, '%Y-%m-%dT%H:%i:%s') AS expires_at,
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

        if (!$user || !(bool) $user['active'] || (bool) $user['expired']) {
            return null;
        }

        if (!$this->passwordMatches($password, (string) $user['password_hash'])) {
            return null;
        }

        $update = $pdo->prepare('UPDATE mobile_users SET last_login_at = NOW() WHERE id = :id');
        $update->execute(['id' => $user['id']]);

        unset($user['password_hash']);

        return $this->normalizePublicUser($user);
    }

    public function update(int $id, array $data): ?array
    {
        $pdo = Database::connection();
        
        $updates = [];
        $params = ['id' => $id];

        if (isset($data['active'])) {
            $updates[] = 'active = :active';
            $params['active'] = (int) $data['active'];
        }

        if (isset($data['validity_amount']) && isset($data['validity_unit'])) {
            $amount = max(1, (int) $data['validity_amount']);
            $unit = $data['validity_unit'] === 'days' ? 'days' : 'months';
            
            $updates[] = 'expires_at = :expires_at';
            $updates[] = 'validity_amount = :validity_amount';
            $updates[] = 'validity_unit = :validity_unit';
            
            $params['expires_at'] = $this->expiresAt($amount, $unit);
            $params['validity_amount'] = $amount;
            $params['validity_unit'] = $unit;
        }

        if (array_key_exists('password', $data)) {
            $password = trim((string) $data['password']);

            if ($password !== '') {
                $updates[] = 'password_hash = :password_hash';
                $params['password_hash'] = $password;
            }
        }

        if (empty($updates)) {
            return $this->findPublic($id);
        }

        $sql = 'UPDATE mobile_users SET ' . implode(', ', $updates) . ' WHERE id = :id AND deleted_at IS NULL';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $this->findPublic($id);
    }

    public function delete(int $id): bool
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('DELETE FROM mobile_users WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }

    public function passwordMatches(string $password, string $storedPassword): bool
    {
        return $storedPassword !== '' && hash_equals($storedPassword, $password);
    }

    private function findPublic(int $id): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT id, name, username, password_hash AS password, validity_amount, validity_unit,
                    DATE_FORMAT(expires_at, '%Y-%m-%dT%H:%i:%s') AS expires_at,
                    active,
                    DATE_FORMAT(created_at, '%Y-%m-%dT%H:%i:%s') AS created_at,
                    CASE WHEN expires_at < NOW() THEN 1 ELSE 0 END AS expired
             FROM mobile_users
             WHERE id = :id AND deleted_at IS NULL
             LIMIT 1"
        );
        $stmt->execute(['id' => $id]);

        return $this->normalizePublicUser($stmt->fetch() ?: []);
    }

    private function usernameExists(string $username): bool
    {
        $stmt = Database::connection()->prepare('SELECT COUNT(*) FROM mobile_users WHERE username = :username AND deleted_at IS NULL');
        $stmt->execute(['username' => $username]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function expiresAt(int $amount, string $unit): string
    {
        return (new DateTimeImmutable())->modify('+' . $amount . ' ' . $unit)->format('Y-m-d H:i:s');
    }

    private function normalizePublicUser(array $user): array
    {
        if (!$user) {
            return [];
        }

        $user['id'] = (int) $user['id'];
        $user['validity_amount'] = (int) $user['validity_amount'];
        $user['active'] = (bool) $user['active'];
        $user['expired'] = (bool) $user['expired'];
        $user['password'] = $user['password'] ?? '';

        return $user;
    }
}
