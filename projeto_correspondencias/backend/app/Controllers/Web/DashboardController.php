<?php

namespace App\Controllers\Web;

class DashboardController
{
    public function index(): void
    {
        if (!isset($_SESSION['admin_id'])) {
            header('Location: login');
            exit;
        }

        $view = dirname(__DIR__, 2) . '/Views/dashboard.php';
        require $view;
    }
}
