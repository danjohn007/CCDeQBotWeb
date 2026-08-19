<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use PDO;

final class ProfileController
{
    public static function show(): never
    {
        $user = Auth::requireAuth();
        Response::success(['usuario' => self::publicUser($user)]);
    }

    public static function update(): never
    {
        $user = Auth::requireAuth();
        $data = Request::json();
        $name = trim((string) ($data['nombre'] ?? ''));
        $lastName = trim((string) ($data['apellidos'] ?? ''));
        $email = mb_strtolower(trim((string) ($data['correo'] ?? '')));

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::error('Revisa tu nombre y correo electrónico.', 422);
        }

        $pdo = Database::connection();
        $exists = $pdo->prepare('SELECT id_usuario FROM usuarios_sistema WHERE correo = :correo AND id_usuario <> :id LIMIT 1');
        $exists->execute([
            'correo' => $email,
            'id' => $user['id_usuario'],
        ]);
        if ($exists->fetch(PDO::FETCH_ASSOC)) {
            Response::error('Ya existe otro usuario con ese correo.', 409);
        }

        $stmt = $pdo->prepare(
            'UPDATE usuarios_sistema
             SET nombre = :nombre, apellidos = :apellidos, correo = :correo
             WHERE id_usuario = :id'
        );
        $stmt->execute([
            'nombre' => $name,
            'apellidos' => $lastName !== '' ? $lastName : null,
            'correo' => $email,
            'id' => $user['id_usuario'],
        ]);

        Audit::log(
            'actualizar_perfil',
            'usuario',
            (int) $user['id_usuario'],
            'Perfil actualizado',
            ['nombre' => $user['nombre'], 'apellidos' => $user['apellidos'] ?? null, 'correo' => $user['correo']],
            ['nombre' => $name, 'apellidos' => $lastName !== '' ? $lastName : null, 'correo' => $email],
            (int) $user['id_usuario']
        );

        Response::success(['usuario' => self::freshUser((int) $user['id_usuario'])], 200, 'Perfil actualizado correctamente.');
    }

    public static function changePassword(): never
    {
        $user = Auth::requireAuth();
        $data = Request::json();
        $current = (string) ($data['password_actual'] ?? '');
        $new = (string) ($data['password_nueva'] ?? '');
        $confirm = (string) ($data['password_confirmacion'] ?? '');

        if ($current === '' || strlen($new) < 8 || $new !== $confirm) {
            Response::error('La contraseña nueva debe tener al menos 8 caracteres y coincidir con la confirmación.', 422);
        }

        if ($current === $new) {
            Response::error('La contraseña nueva debe ser diferente a la actual.', 422);
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT password_hash FROM usuarios_sistema WHERE id_usuario = :id LIMIT 1');
        $stmt->execute(['id' => $user['id_usuario']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || !password_verify($current, (string) $row['password_hash'])) {
            Response::error('La contraseña actual no es correcta.', 422);
        }

        $update = $pdo->prepare(
            'UPDATE usuarios_sistema
             SET password_hash = :password, intentos_fallidos = 0, bloqueado_hasta = NULL
             WHERE id_usuario = :id'
        );
        $update->execute([
            'password' => password_hash($new, PASSWORD_DEFAULT),
            'id' => $user['id_usuario'],
        ]);

        Audit::log('cambiar_password', 'usuario', (int) $user['id_usuario'], 'Contraseña actualizada', null, null, (int) $user['id_usuario']);
        Response::success(null, 200, 'Contraseña actualizada correctamente.');
    }

    public static function uploadPhoto(): never
    {
        $user = Auth::requireAuth();
        $file = $_FILES['foto_perfil'] ?? $_FILES['avatar'] ?? null;
        if (!$file || !is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            Response::error('Selecciona una imagen válida para tu perfil.', 422);
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > 2 * 1024 * 1024) {
            Response::error('La imagen no debe superar 2 MB.', 422);
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        $mime = mime_content_type($tmp) ?: '';
        $extensions = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];
        if (!isset($extensions[$mime])) {
            Response::error('Solo se permiten imágenes JPG, PNG o WEBP.', 422);
        }

        $storageDir = dirname(__DIR__, 2) . '/storage/perfiles';
        if (!is_dir($storageDir) && !mkdir($storageDir, 0755, true) && !is_dir($storageDir)) {
            Response::error('No fue posible preparar la carpeta de perfiles.', 500);
        }

        $htaccess = $storageDir . '/.htaccess';
        if (!is_file($htaccess)) {
            file_put_contents($htaccess, "Options -Indexes\n<FilesMatch \"\\.(php|phtml|phar)$\">\n    Require all denied\n</FilesMatch>\n");
        }

        $filename = 'perfil_' . (int) $user['id_usuario'] . '_' . bin2hex(random_bytes(8)) . '.' . $extensions[$mime];
        $destination = $storageDir . '/' . $filename;
        if (!move_uploaded_file($tmp, $destination)) {
            Response::error('No fue posible guardar la imagen.', 500);
        }
        @chmod($destination, 0644);

        $publicUrl = '/ccdeqbot/back/storage/perfiles/' . $filename;
        $pdo = Database::connection();

        if (!empty($user['foto_perfil']) && str_starts_with((string) $user['foto_perfil'], '/ccdeqbot/back/storage/perfiles/')) {
            $oldPath = dirname(__DIR__, 2) . '/storage/perfiles/' . basename((string) $user['foto_perfil']);
            if (is_file($oldPath)) {
                @unlink($oldPath);
            }
        }

        $stmt = $pdo->prepare('UPDATE usuarios_sistema SET foto_perfil = :foto WHERE id_usuario = :id');
        $stmt->execute([
            'foto' => $publicUrl,
            'id' => $user['id_usuario'],
        ]);

        Audit::log('actualizar_foto_perfil', 'usuario', (int) $user['id_usuario'], 'Foto de perfil actualizada', null, ['foto_perfil' => $publicUrl], (int) $user['id_usuario']);
        Response::success(['usuario' => self::freshUser((int) $user['id_usuario'])], 200, 'Foto de perfil actualizada.');
    }

    private static function freshUser(int $id): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id_usuario, nombre, apellidos, correo, rol, activo, ultimo_acceso, foto_perfil
             FROM usuarios_sistema WHERE id_usuario = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return self::publicUser($user ?: []);
    }

    private static function publicUser(array $user): array
    {
        return [
            'id_usuario' => (int) ($user['id_usuario'] ?? 0),
            'nombre' => $user['nombre'] ?? '',
            'apellidos' => $user['apellidos'] ?? null,
            'correo' => $user['correo'] ?? '',
            'rol' => $user['rol'] ?? '',
            'ultimo_acceso' => $user['ultimo_acceso'] ?? null,
            'foto_perfil' => $user['foto_perfil'] ?? null,
        ];
    }
}
