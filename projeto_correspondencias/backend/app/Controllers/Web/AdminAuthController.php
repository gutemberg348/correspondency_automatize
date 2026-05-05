<?php

namespace App\Controllers\Web;

use App\Models\User;

class AdminAuthController
{
    public function login(): void
    {
        if (isset($_SESSION['admin_id'])) {
            header('Location: ./');
            exit;
        }

        $view = dirname(__DIR__, 2) . '/Views/login.php';
        require $view;
    }

    public function authenticate(): void
    {
        try {
            $data = json_decode(file_get_contents('php://input') ?: '[]', true) ?: $_POST;
            $valid = (new User())->attempt((string) ($data['username'] ?? ''), (string) ($data['password'] ?? ''));

            if (!$valid) {
                $this->json(['error' => 'Credenciais invalidas'], 401);
                return;
            }

            session_regenerate_id(true);
            $_SESSION['admin_id'] = (new User())->findIdByUsername((string) $data['username']);
            $_SESSION['admin_username'] = strtolower(trim((string) $data['username']));

            $this->json(['ok' => true]);
        } catch (\Throwable $error) {
            $payload = ['error' => 'Erro no login admin'];

            if ($this->debugEnabled()) {
                $payload['debug'] = [
                    'type' => $error::class,
                    'message' => $error->getMessage(),
                    'file' => $error->getFile(),
                    'line' => $error->getLine(),
                ];
            }

            $this->json($payload, 500);
        }
    }

    public function logout(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }

        session_destroy();
        header('Location: login');
        exit;
    }

    private function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_UNESCAPED_SLASHES);
    }

    private function debugEnabled(): bool
    {
        $config = require dirname(__DIR__, 3) . '/config.php';
        return (bool) ($config['admin_debug_errors'] ?? false);
    }
}
