<?php

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
$query = parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY);

if ($path === '/') {
    header('Location: /cordova_app/www/login.html');
    exit;
}

$allowedStaticPrefixes = [
    '/cordova_app/www/',
    '/backend/public/assets/',
];

foreach ($allowedStaticPrefixes as $prefix) {
    if (str_starts_with($path, $prefix)) {
        $file = realpath(__DIR__ . $path);
        $base = realpath(__DIR__ . $prefix);

        if (!$file || !$base || !str_starts_with($file, $base) || !is_file($file)) {
            break;
        }

        $types = [
            'css' => 'text/css',
            'js' => 'application/javascript',
            'html' => 'text/html',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'svg' => 'image/svg+xml',
            'xml' => 'application/xml',
        ];
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        header('Content-Type: ' . ($types[$extension] ?? 'application/octet-stream'));
        readfile($file);
        return true;
    }
}

if (str_starts_with($path, '/backend/public')) {
    $relative = substr($path, strlen('/backend/public')) ?: '/';
    $_SERVER['SCRIPT_NAME'] = '/backend/public/index.php';
    $_SERVER['REQUEST_URI'] = $relative . ($query ? '?' . $query : '');
    require __DIR__ . '/backend/public/index.php';
    return true;
}

http_response_code(404);
header('Content-Type: text/plain');
echo 'Not found';
