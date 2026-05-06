<?php

namespace App\Controllers\Api;

use App\Core\AuthGuard;
use App\Models\Package;

class PackageController
{
    public function index(): void
    {
        $actor = AuthGuard::requireAdminSessionOrMobileJwt();
        $this->json((new Package())->all($actor));
    }

    public function store(): void
    {
        $actor = AuthGuard::requireAdminSessionOrMobileJwt();
        $data = json_decode(file_get_contents('php://input') ?: '[]', true) ?: [];
        $package = (new Package())->create($data, $actor);
        $this->json($package, 201);
    }

    public function deliver(string $id): void
    {
        $actor = AuthGuard::requireAdminSessionOrMobileJwt();
        $data = json_decode(file_get_contents('php://input') ?: '[]', true) ?: [];
        $package = (new Package())->deliver($id, $data, $actor);

        if (!$package) {
            $this->json(['error' => 'Correspondencia nao encontrada'], 404);
            return;
        }

        $this->json($package);
    }

    private function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_UNESCAPED_SLASHES);
    }
}
