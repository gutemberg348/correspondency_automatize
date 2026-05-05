<?php

namespace App\Controllers\Api;

use App\Core\AuthGuard;
use App\Models\MobileUser;
use InvalidArgumentException;

class MobileUserController
{
    public function index(): void
    {
        AuthGuard::requireAdminSession();
        $this->json((new MobileUser())->all());
    }

    public function store(): void
    {
        AuthGuard::requireAdminSession();
        $data = json_decode(file_get_contents('php://input') ?: '[]', true) ?: [];

        try {
            $user = (new MobileUser())->create($data);
        } catch (InvalidArgumentException $exception) {
            $this->json(['error' => $exception->getMessage()], 422);
            return;
        }

        $this->json($user, 201);
    }

    public function update(string $id): void
    {
        AuthGuard::requireAdminSession();
        $data = json_decode(file_get_contents('php://input') ?: '[]', true) ?: [];

        try {
            $user = (new MobileUser())->update((int) $id, $data);
        } catch (InvalidArgumentException $exception) {
            $this->json(['error' => $exception->getMessage()], 422);
            return;
        }

        if (!$user) {
            $this->json(['error' => 'Usuario nao encontrado'], 404);
            return;
        }

        $this->json($user);
    }

    public function delete(string $id): void
    {
        AuthGuard::requireAdminSession();

        $deleted = (new MobileUser())->delete((int) $id);

        if (!$deleted) {
            $this->json(['error' => 'Usuario nao encontrado ou ja excluido'], 404);
            return;
        }

        $this->json(['ok' => true]);
    }

    private function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_UNESCAPED_SLASHES);
    }
}