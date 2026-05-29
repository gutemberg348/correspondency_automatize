<?php

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

session_start();

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path = dirname(__DIR__) . '/app/' . str_replace('\\', '/', $relative) . '.php';
    if (file_exists($path)) {
        require $path;
    }
});

use App\Controllers\Api\AuthController;
use App\Controllers\Api\MobileDeviceController;
use App\Controllers\Api\MobileUserController;
use App\Controllers\Api\PackageController;
use App\Controllers\Web\AdminAuthController;
use App\Controllers\Web\DashboardController;
use App\Core\Router;

$router = new Router();
$router->get('/', [DashboardController::class, 'index']);
$router->get('/login', [AdminAuthController::class, 'login']);
$router->post('/login', [AdminAuthController::class, 'authenticate']);
$router->get('/logout', [AdminAuthController::class, 'logout']);
$router->post('/api/mobile-login', [AuthController::class, 'mobileLogin']);

// --- ROTAS DE USUÁRIOS MOBILE ---
$router->get('/api/mobile-users', [MobileUserController::class, 'index']);
$router->post('/api/mobile-users', [MobileUserController::class, 'store']);
$router->post('/api/mobile-users/{id}', [MobileUserController::class, 'update']);
$router->delete('/api/mobile-users/{id}', [MobileUserController::class, 'delete']);

// --- ROTAS DE DISPOSITIVOS ---
$router->get('/api/mobile-devices', [MobileDeviceController::class, 'index']);
$router->post('/api/mobile-devices/{id}', [MobileDeviceController::class, 'update']);
$router->post('/api/mobile-devices/{id}/approve', [MobileDeviceController::class, 'approve']);
$router->post('/api/mobile-devices/{id}/block', [MobileDeviceController::class, 'block']);
$router->delete('/api/mobile-devices/{id}', [MobileDeviceController::class, 'delete']);

// --- ROTAS DE PACOTES ---
$router->get('/api/packages', [PackageController::class, 'index']);
$router->post('/api/packages', [PackageController::class, 'store']);
$router->post('/api/packages/{id}/deliver', [PackageController::class, 'deliver']);

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
