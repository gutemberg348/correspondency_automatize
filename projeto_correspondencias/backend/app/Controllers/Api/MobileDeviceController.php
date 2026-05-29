<?php

namespace App\Controllers\Api;

use App\Core\AuthGuard;
use App\Models\MobileDevice;

class MobileDeviceController
{
    public function index(): void
    {
        AuthGuard::requireAdminSession();
        $this->json((new MobileDevice())->all());
    }

    public function approve(string $id): void
    {
        AuthGuard::requireAdminSession();
        $device = (new MobileDevice())->approve((int) $id, (int) ($_SESSION['admin_id'] ?? 0));

        if (!$device) {
            $this->json(['error' => 'Dispositivo nao encontrado'], 404);
            return;
        }

        $this->json($device);
    }

    public function block(string $id): void
    {
        AuthGuard::requireAdminSession();
        $device = (new MobileDevice())->block((int) $id);

        if (!$device) {
            $this->json(['error' => 'Dispositivo nao encontrado'], 404);
            return;
        }

        $this->json($device);
    }

    public function update(string $id): void
    {
        AuthGuard::requireAdminSession();
        $data = json_decode(file_get_contents('php://input') ?: '[]', true) ?: [];
        $device = (new MobileDevice())->updatePhone((int) $id, (string) ($data['phone'] ?? ''));

        if (!$device) {
            $this->json(['error' => 'Dispositivo nao encontrado'], 404);
            return;
        }

        $this->json($device);
    }

    public function delete(string $id): void
    {
        AuthGuard::requireAdminSession();

        $deleted = (new MobileDevice())->delete((int) $id);

        if (!$deleted) {
            $this->json(['error' => 'Dispositivo nao encontrado ou ja excluido'], 404);
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
