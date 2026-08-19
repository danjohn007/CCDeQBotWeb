<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use DateTimeImmutable;
use PDO;

final class AuthController
{
    public static function login(): never
    {
        $data = Request::json();
        $email = mb_strtolower(trim((string) ($data['correo'] ?? '')));
        $password = (string) ($data['password'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
            Response::error('Captura un correo y una contraseña válidos.', 422);
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM usuarios_sistema WHERE correo = :correo LIMIT 1');
        $stmt->execute(['correo' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !(int) $user['activo']) {
            usleep(250000);
            Response::error('El correo o la contraseña son incorrectos.', 401);
        }

        if (!empty($user['bloqueado_hasta']) && new DateTimeImmutable($user['bloqueado_hasta']) > new DateTimeImmutable()) {
            Response::error('La cuenta está bloqueada temporalmente por varios intentos fallidos.', 423);
        }

        if (!password_verify($password, $user['password_hash'])) {
            $attempts = (int) $user['intentos_fallidos'] + 1;
            $blockedUntil = $attempts >= 5 ? date('Y-m-d H:i:s', time() + 15 * 60) : null;
            $update = $pdo->prepare(
                'UPDATE usuarios_sistema SET intentos_fallidos = :intentos, bloqueado_hasta = :bloqueado WHERE id_usuario = :id'
            );
            $update->execute([
                'intentos' => $attempts,
                'bloqueado' => $blockedUntil,
                'id' => $user['id_usuario'],
            ]);
            Audit::log('login_fallido', 'usuario', (int) $user['id_usuario'], 'Intento de acceso fallido', null, null, (int) $user['id_usuario']);
            Response::error('El correo o la contraseña son incorrectos.', 401);
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id_usuario'];
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        $pdo->prepare(
            'UPDATE usuarios_sistema SET intentos_fallidos = 0, bloqueado_hasta = NULL, ultimo_acceso = NOW() WHERE id_usuario = :id'
        )->execute(['id' => $user['id_usuario']]);

        Audit::log('login', 'usuario', (int) $user['id_usuario'], 'Inicio de sesión correcto');
        Response::success([
            'usuario' => self::publicUser($user),
            'csrf_token' => Csrf::token(),
        ], 200, 'Sesión iniciada correctamente.');
    }

    public static function me(): never
    {
        $user = Auth::requireAuth();
        Response::success([
            'usuario' => self::publicUser($user),
            'csrf_token' => Csrf::token(),
        ]);
    }

    public static function logout(): never
    {
        $user = Auth::requireAuth();
        Audit::log('logout', 'usuario', (int) $user['id_usuario'], 'Cierre de sesión');
        Auth::destroySession();
        Response::success(null, 200, 'Sesión cerrada correctamente.');
    }

    private static function publicUser(array $user): array
    {
        return [
            'id_usuario' => (int) $user['id_usuario'],
            'nombre' => $user['nombre'],
            'apellidos' => $user['apellidos'] ?? null,
            'correo' => $user['correo'],
            'rol' => $user['rol'],
            'ultimo_acceso' => $user['ultimo_acceso'] ?? null,
            'foto_perfil' => $user['foto_perfil'] ?? null,
        ];
    }
}
