<?php

declare(strict_types=1);

// En cPanel cambia esta ruta para apuntar a la carpeta privada backend/app.
define('APP_PATH', dirname(__DIR__) . '/app');
require APP_PATH . '/bootstrap.php';

use App\Controllers\AuthController;
use App\Controllers\ContactController;
use App\Controllers\DashboardController;
use App\Controllers\MessageController;
use App\Controllers\QuoteController;
use App\Controllers\ProductController;
use App\Controllers\ProfileController;
use App\Controllers\UserController;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Response;

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$base = (string) ($GLOBALS['app_config']['base_path'] ?? '/api');
$path = preg_replace('#^' . preg_quote($base, '#') . '#', '', $uri) ?: '/';
$path = '/' . trim($path, '/');

try {
    if ($method === 'GET' && $path === '/health') {
        Database::connection()->query('SELECT 1');
        Response::success([
            'servicio' => 'CCdeQbot CRM API',
            'estado' => 'ok',
            'fecha' => date(DATE_ATOM),
        ]);
    }

    if ($method === 'POST' && $path === '/auth/login') {
        AuthController::login();
    }

    Auth::requireAuth();
    if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
        Csrf::verify();
    }

    if ($method === 'GET' && $path === '/auth/me') AuthController::me();
    if ($method === 'POST' && $path === '/auth/logout') AuthController::logout();
    if ($method === 'GET' && $path === '/perfil') ProfileController::show();
    if ($method === 'PUT' && $path === '/perfil') ProfileController::update();
    if ($method === 'PATCH' && $path === '/perfil/password') ProfileController::changePassword();
    if ($method === 'POST' && $path === '/perfil/foto') ProfileController::uploadPhoto();
    if ($method === 'GET' && $path === '/dashboard/resumen') DashboardController::summary();
    if ($method === 'GET' && $path === '/mensajes/resumen') MessageController::summary();
    if ($method === 'POST' && $path === '/mensajes/enviar') MessageController::send();
    if ($method === 'GET' && $path === '/contactos') ContactController::index();
    if ($method === 'GET' && preg_match('#^/contactos/(\d+)$#', $path, $m)) ContactController::show((int) $m[1]);
    if ($method === 'PUT' && preg_match('#^/contactos/(\d+)$#', $path, $m)) ContactController::update((int) $m[1]);
    if ($method === 'PATCH' && preg_match('#^/contactos/(\d+)/clasificacion$#', $path, $m)) ContactController::classify((int) $m[1]);
    if ($method === 'POST' && preg_match('#^/contactos/(\d+)/notas$#', $path, $m)) ContactController::addNote((int) $m[1]);
    if ($method === 'POST' && preg_match('#^/contactos/(\d+)/etiquetas$#', $path, $m)) ContactController::addTag((int) $m[1]);
    if ($method === 'DELETE' && preg_match('#^/contactos/(\d+)/etiquetas/(\d+)$#', $path, $m)) ContactController::removeTag((int) $m[1], (int) $m[2]);
    if ($method === 'GET' && $path === '/cotizaciones') QuoteController::index();
    if ($method === 'POST' && preg_match('#^/contactos/(\d+)/cotizaciones$#', $path, $m)) QuoteController::createForContact((int) $m[1]);
    if ($method === 'GET' && preg_match('#^/cotizaciones/(\d+)/conversacion$#', $path, $m)) QuoteController::conversation((int) $m[1]);
    if ($method === 'POST' && preg_match('#^/cotizaciones/(\d+)/archivo$#', $path, $m)) QuoteController::uploadFile((int) $m[1]);
    if ($method === 'PATCH' && preg_match('#^/cotizaciones/(\d+)/estado$#', $path, $m)) QuoteController::updateState((int) $m[1]);
    if ($method === 'GET' && $path === '/productos') ProductController::index();
    if ($method === 'POST' && $path === '/productos') ProductController::create();
    if ($method === 'GET' && preg_match('#^/productos/(\d+)$#', $path, $m)) ProductController::show((int) $m[1]);
    if ($method === 'PUT' && preg_match('#^/productos/(\d+)$#', $path, $m)) ProductController::update((int) $m[1]);
    if ($method === 'PATCH' && preg_match('#^/productos/(\d+)/archivar$#', $path, $m)) ProductController::archive((int) $m[1]);
    if ($method === 'PATCH' && preg_match('#^/productos/(\d+)/desarchivar$#', $path, $m)) ProductController::restore((int) $m[1]);
    if ($method === 'DELETE' && preg_match('#^/productos/(\d+)$#', $path, $m)) ProductController::delete((int) $m[1]);
    if ($method === 'GET' && $path === '/usuarios') UserController::index();
    if ($method === 'POST' && $path === '/usuarios') UserController::create();
    if ($method === 'PATCH' && preg_match('#^/usuarios/(\d+)/estatus$#', $path, $m)) UserController::toggleStatus((int) $m[1]);
    if ($method === 'GET' && $path === '/catalogos/asesores') UserController::advisors();
    if ($method === 'GET' && $path === '/catalogos/etiquetas') UserController::tags();

    Response::error('La ruta solicitada no existe.', 404);
} catch (Throwable $e) {
    error_log($e->__toString());
    $isDev = ($GLOBALS['app_config']['app_env'] ?? 'production') === 'development';
    Response::error(
        $isDev ? $e->getMessage() : 'Ocurrió un error interno. Inténtalo nuevamente.',
        500
    );
}
