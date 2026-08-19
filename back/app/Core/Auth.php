<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

final class Auth
{
    public static function id(): ?int
    {
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    public static function user(): ?array
    {
        $id = self::id();
        if (!$id) {
            return null;
        }

        $stmt = Database::connection()->prepare(
            'SELECT id_usuario, nombre, apellidos, correo, rol, activo, ultimo_acceso, foto_perfil
             FROM usuarios_sistema WHERE id_usuario = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !(int) $user['activo']) {
            self::destroySession();
            return null;
        }

        return $user;
    }

    public static function requireAuth(): array
    {
        $user = self::user();
        if (!$user) {
            Response::error('Debes iniciar sesión para continuar.', 401);
        }
        return $user;
    }

    public static function requireRoles(array $roles): array
    {
        $user = self::requireAuth();
        if (!in_array($user['rol'], $roles, true)) {
            Response::error('No tienes permisos para realizar esta acción.', 403);
        }
        return $user;
    }

    public static function destroySession(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }
}
