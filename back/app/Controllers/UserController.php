<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use PDO;

final class UserController
{
    private const ROLES = ['superadministrador', 'administrador', 'asesor', 'consulta'];
    private const ADMIN_CREATABLE_ROLES = ['asesor', 'consulta'];

    public static function index(): never
    {
        Auth::requireRoles(['superadministrador', 'administrador']);
        $rows = Database::connection()->query(
            'SELECT id_usuario, nombre, apellidos, correo, rol, activo, ultimo_acceso, creado_en
             FROM usuarios_sistema
             ORDER BY FIELD(rol, \'superadministrador\', \'administrador\', \'asesor\', \'consulta\'),
                      activo DESC, nombre, apellidos'
        )->fetchAll(PDO::FETCH_ASSOC);
        Response::success($rows);
    }

    public static function create(): never
    {
        $manager = Auth::requireRoles(['superadministrador', 'administrador']);
        $data = Request::json();
        $name = trim((string) ($data['nombre'] ?? ''));
        $lastName = trim((string) ($data['apellidos'] ?? ''));
        $email = mb_strtolower(trim((string) ($data['correo'] ?? '')));
        $password = (string) ($data['password'] ?? '');
        $role = (string) ($data['rol'] ?? 'asesor');

        if (!in_array($role, self::ROLES, true)) {
            Response::error('El rol seleccionado no es válido.', 422);
        }

        if ($manager['rol'] !== 'superadministrador' && !in_array($role, self::ADMIN_CREATABLE_ROLES, true)) {
            Response::error('Solo el superadministrador puede crear administradores o superadministradores.', 403);
        }

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
            Response::error('Revisa el nombre, correo y una contraseña de al menos 8 caracteres.', 422);
        }

        try {
            $stmt = Database::connection()->prepare(
                'INSERT INTO usuarios_sistema (nombre, apellidos, correo, password_hash, rol)
                 VALUES (:nombre, :apellidos, :correo, :password, :rol)'
            );
            $stmt->execute([
                'nombre' => $name,
                'apellidos' => $lastName !== '' ? $lastName : null,
                'correo' => $email,
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'rol' => $role,
            ]);
        } catch (\PDOException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                Response::error('Ya existe un usuario con ese correo.', 409);
            }
            throw $e;
        }

        $id = (int) Database::connection()->lastInsertId();
        Audit::log(
            'crear',
            'usuario',
            $id,
            'Usuario creado',
            null,
            ['correo' => $email, 'rol' => $role],
            (int) $manager['id_usuario']
        );
        Response::success(['id_usuario' => $id], 201, 'Usuario creado correctamente.');
    }

    public static function toggleStatus(int $id): never
    {
        $manager = Auth::requireRoles(['superadministrador', 'administrador']);
        if ((int) $manager['id_usuario'] === $id) {
            Response::error('No puedes desactivar tu propia cuenta.', 422);
        }

        $pdo = Database::connection();
        $targetStmt = $pdo->prepare(
            'SELECT id_usuario, nombre, correo, rol, activo FROM usuarios_sistema WHERE id_usuario = :id LIMIT 1'
        );
        $targetStmt->execute(['id' => $id]);
        $target = $targetStmt->fetch(PDO::FETCH_ASSOC);
        if (!$target) {
            Response::error('El usuario no existe.', 404);
        }

        if ($manager['rol'] !== 'superadministrador' && in_array($target['rol'], ['superadministrador', 'administrador'], true)) {
            Response::error('Un administrador no puede modificar cuentas de nivel superior o equivalente.', 403);
        }

        $active = !empty(Request::json()['activo']) ? 1 : 0;
        if ($target['rol'] === 'superadministrador' && $active === 0) {
            $activeSuperAdmins = (int) $pdo->query(
                "SELECT COUNT(*) FROM usuarios_sistema WHERE rol = 'superadministrador' AND activo = 1"
            )->fetchColumn();
            if ($activeSuperAdmins <= 1) {
                Response::error('Debe permanecer al menos un superadministrador activo.', 422);
            }
        }

        $stmt = $pdo->prepare('UPDATE usuarios_sistema SET activo = :activo WHERE id_usuario = :id');
        $stmt->execute(['activo' => $active, 'id' => $id]);
        Audit::log(
            'cambiar_estatus',
            'usuario',
            $id,
            $active ? 'Usuario activado' : 'Usuario desactivado',
            ['activo' => (int) $target['activo'], 'rol' => $target['rol']],
            ['activo' => $active, 'rol' => $target['rol']],
            (int) $manager['id_usuario']
        );
        Response::success(null, 200, $active ? 'Usuario activado.' : 'Usuario desactivado.');
    }

    public static function advisors(): never
    {
        Auth::requireAuth();
        $rows = Database::connection()->query(
            "SELECT id_usuario, CONCAT_WS(' ', nombre, apellidos) nombre_completo
             FROM usuarios_sistema
             WHERE activo = 1 AND rol IN ('superadministrador','administrador','asesor')
             ORDER BY FIELD(rol, 'asesor', 'administrador', 'superadministrador'), nombre, apellidos"
        )->fetchAll(PDO::FETCH_ASSOC);
        Response::success($rows);
    }

    public static function tags(): never
    {
        Auth::requireAuth();
        $rows = Database::connection()->query(
            'SELECT id_etiqueta, nombre, slug, color_fondo, color_texto FROM etiquetas WHERE activo = 1 ORDER BY nombre'
        )->fetchAll(PDO::FETCH_ASSOC);
        Response::success($rows);
    }
}
