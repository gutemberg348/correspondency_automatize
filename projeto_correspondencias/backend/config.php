<?php

$envPath = __DIR__ . '/.env';
if (is_file($envPath)) {
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value, " \t\n\r\0\x0B\"'");

        if ($key !== '' && getenv($key) === false) {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

$envBool = static function (string $key, bool $default = false): bool {
    $value = getenv($key);
    if ($value === false) {
        return $default;
    }

    return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
};

return [
    'app_name' => 'Gestao de Correspondencias',
    'jwt_secret' => getenv('JWT_SECRET') ?: 'troque-este-segredo',
    'db_host' => getenv('DB_HOST') ?: '127.0.0.1',
    'db_name' => getenv('DB_NAME') ?: 'correspondencias',
    'db_user' => getenv('DB_USER') ?: 'root',
    'db_pass' => getenv('DB_PASS') ?: '',
    'db_charset' => getenv('DB_CHARSET') ?: 'utf8mb4',
    'admin_debug_errors' => $envBool('ADMIN_DEBUG_ERRORS'),
];
