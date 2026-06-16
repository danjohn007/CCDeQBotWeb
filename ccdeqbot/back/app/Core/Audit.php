<?php

declare(strict_types=1);

namespace App\Core;

final class Audit
{
    public static function log(
        string $action,
        string $entity,
        ?int $entityId = null,
        ?string $description = null,
        mixed $before = null,
        mixed $after = null,
        ?int $userId = null
    ): void {
        try {
            $stmt = Database::connection()->prepare(
                'INSERT INTO auditoria
                 (id_usuario, accion, entidad, id_entidad, descripcion, ip, user_agent, datos_anteriores, datos_nuevos)
                 VALUES (:usuario, :accion, :entidad, :id_entidad, :descripcion, :ip, :user_agent, :antes, :despues)'
            );
            $stmt->execute([
                'usuario' => $userId ?? Auth::id(),
                'accion' => $action,
                'entidad' => $entity,
                'id_entidad' => $entityId,
                'descripcion' => $description,
                'ip' => Request::ip(),
                'user_agent' => Request::userAgent(),
                'antes' => $before === null ? null : json_encode($before, JSON_UNESCAPED_UNICODE),
                'despues' => $after === null ? null : json_encode($after, JSON_UNESCAPED_UNICODE),
            ]);
        } catch (\Throwable $e) {
            error_log('Audit error: ' . $e->getMessage());
        }
    }
}
