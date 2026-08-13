<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use PDO;

final class VideoController
{
    public static function index(): never
    {
        Auth::requireRoles(['superadministrador']);
        $rows = Database::connection()->query(
            'SELECT id_video, titulo, url, activo, creado_en
             FROM videos
             ORDER BY creado_en DESC'
        )->fetchAll(PDO::FETCH_ASSOC);
        Response::success($rows);
    }

    public static function create(): never
    {
        $user = Auth::requireRoles(['superadministrador']);
        $data = Request::json();
        $title = trim((string) ($data['titulo'] ?? ''));
        $url = trim((string) ($data['url'] ?? ''));

        if ($title === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            Response::error('Revisa el título y la URL del vídeo.', 422);
        }

        $stmt = Database::connection()->prepare(
            'INSERT INTO videos (titulo, url) VALUES (:titulo, :url)'
        );
        $stmt->execute(['titulo' => $title, 'url' => $url]);

        $id = (int) Database::connection()->lastInsertId();
        Audit::log(
            'crear',
            'video',
            $id,
            'Vídeo creado',
            null,
            ['titulo' => $title, 'url' => $url],
            (int) $user['id_usuario']
        );
        Response::success(['id_video' => $id], 201, 'Vídeo agregado correctamente.');
    }

    public static function update(int $id): never
    {
        $user = Auth::requireRoles(['superadministrador']);
        $data = Request::json();
        $title = trim((string) ($data['titulo'] ?? ''));
        $url = trim((string) ($data['url'] ?? ''));

        if ($title === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            Response::error('Revisa el título y la URL del vídeo.', 422);
        }

        $pdo = Database::connection();
        $targetStmt = $pdo->prepare('SELECT id_video, titulo, url, activo FROM videos WHERE id_video = :id LIMIT 1');
        $targetStmt->execute(['id' => $id]);
        $target = $targetStmt->fetch(PDO::FETCH_ASSOC);
        if (!$target) {
            Response::error('El vídeo no existe.', 404);
        }

        $stmt = $pdo->prepare('UPDATE videos SET titulo = :titulo, url = :url WHERE id_video = :id');
        $stmt->execute(['titulo' => $title, 'url' => $url, 'id' => $id]);
        Audit::log(
            'actualizar',
            'video',
            $id,
            'Vídeo actualizado',
            ['titulo' => $target['titulo'], 'url' => $target['url']],
            ['titulo' => $title, 'url' => $url],
            (int) $user['id_usuario']
        );
        Response::success(null, 200, 'Vídeo actualizado.');
    }

    public static function toggleStatus(int $id): never
    {
        $user = Auth::requireRoles(['superadministrador']);
        $pdo = Database::connection();
        $targetStmt = $pdo->prepare('SELECT id_video, titulo, activo FROM videos WHERE id_video = :id LIMIT 1');
        $targetStmt->execute(['id' => $id]);
        $target = $targetStmt->fetch(PDO::FETCH_ASSOC);
        if (!$target) {
            Response::error('El vídeo no existe.', 404);
        }

        $active = !empty(Request::json()['activo']) ? 1 : 0;
        $stmt = $pdo->prepare('UPDATE videos SET activo = :activo WHERE id_video = :id');
        $stmt->execute(['activo' => $active, 'id' => $id]);
        Audit::log(
            'cambiar_estatus',
            'video',
            $id,
            $active ? 'Vídeo activado' : 'Vídeo desactivado',
            ['activo' => (int) $target['activo']],
            ['activo' => $active],
            (int) $user['id_usuario']
        );
        Response::success(null, 200, $active ? 'Vídeo activado.' : 'Vídeo desactivado.');
    }

    public static function delete(int $id): never
    {
        $user = Auth::requireRoles(['superadministrador']);
        $pdo = Database::connection();
        $targetStmt = $pdo->prepare('SELECT id_video, titulo FROM videos WHERE id_video = :id LIMIT 1');
        $targetStmt->execute(['id' => $id]);
        $target = $targetStmt->fetch(PDO::FETCH_ASSOC);
        if (!$target) {
            Response::error('El vídeo no existe.', 404);
        }

        $stmt = $pdo->prepare('DELETE FROM videos WHERE id_video = :id');
        $stmt->execute(['id' => $id]);
        Audit::log(
            'eliminar',
            'video',
            $id,
            'Vídeo eliminado',
            ['titulo' => $target['titulo']],
            null,
            (int) $user['id_usuario']
        );
        Response::success(null, 200, 'Vídeo eliminado.');
    }
}