<?php

declare(strict_types=1);

$appPath = defined('APP_PATH') ? APP_PATH : __DIR__;
$GLOBALS['app_config'] = require $appPath . '/config/config.php';

spl_autoload_register(static function (string $class) use ($appPath): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $file = $appPath . '/' . $relative . '.php';
    if (is_file($file)) {
        require $file;
    }
});

$config = $GLOBALS['app_config'];
date_default_timezone_set((string) $config['timezone']);

$origin = $_SERVER['HTTP_ORIGIN'] ?? null;
$allowedOrigins = $config['allowed_origins'] ?? [];
$currentHost = $_SERVER['HTTP_HOST'] ?? '';
$originHost = $origin ? (parse_url($origin, PHP_URL_HOST) ?: '') : '';

if ($origin && (in_array($origin, $allowedOrigins, true) || ($originHost !== '' && $originHost === preg_replace('/:\\d+$/', '', $currentHost)))) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
}

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store, private');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
session_name((string) $config['session']['name']);
session_set_cookie_params([
    'lifetime' => (int) $config['session']['lifetime'],
    'path' => '/ccdeqbot',
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Lax',
]);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
