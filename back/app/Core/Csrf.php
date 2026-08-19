<?php

declare(strict_types=1);

namespace App\Core;

final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return (string) $_SESSION['csrf_token'];
    }

    public static function verify(): void
    {
        $provided = Request::header('X-CSRF-Token');
        $stored = $_SESSION['csrf_token'] ?? '';

        if (!is_string($provided) || !is_string($stored) || $stored === '' || !hash_equals($stored, $provided)) {
            Response::error('La sesión de seguridad no es válida. Actualiza la página e inténtalo nuevamente.', 419);
        }
    }
}
