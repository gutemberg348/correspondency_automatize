<?php

namespace App\Models;

use App\Core\Database;

class User
{
    public function attempt(string $username, string $password): bool
    {
        $pdo = Database::connection();
        $username = strtolower(trim($username));
        $stmt = $pdo->prepare('SELECT id, password_hash FROM admins WHERE username = :username AND active = 1 LIMIT 1');
        $stmt->execute(['username' => $username]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, (string) $admin['password_hash'])) {
            $this->touchLastLogin((int) $admin['id']);
            return true;
        }

        return false;
    }

    public function findIdByUsername(string $username): ?int
    {
        $stmt = Database::connection()->prepare('SELECT id FROM admins WHERE username = :username AND active = 1 LIMIT 1');
        $stmt->execute(['username' => strtolower(trim($username))]);
        $id = $stmt->fetchColumn();

        return $id ? (int) $id : null;
    }

    private function touchLastLogin(int $id): void
    {
        $update = Database::connection()->prepare('UPDATE admins SET last_login_at = NOW() WHERE id = :id');
        $update->execute(['id' => $id]);
    }
}
