<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use PDO;

final class ContactController
{
    private const TYPES = ['lead', 'cliente_pendiente', 'cliente'];
    private const STAGES = [
        'nuevo', 'informacion_solicitada', 'interesado', 'cotizacion_solicitada',
        'cotizacion_enviada', 'seguimiento', 'compra_confirmada', 'no_interesado',
        'cerrado', 'descartado'
    ];

    public static function index(): never
    {
        Auth::requireAuth();
        $pdo = Database::connection();
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = min(100, max(5, (int) ($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;
        $where = ['c.activo = 1'];
        $params = [];

        $q = trim((string) ($_GET['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(c.nombre_completo LIKE :q OR c.whatsapp LIKE :q OR c.correo LIKE :q OR c.empresa LIKE :q)';
            $params['q'] = '%' . $q . '%';
        }

        $type = (string) ($_GET['tipo'] ?? '');
        if (in_array($type, self::TYPES, true)) {
            $where[] = 'c.tipo_contacto = :tipo';
            $params['tipo'] = $type;
        }

        $stage = (string) ($_GET['etapa'] ?? '');
        if (in_array($stage, self::STAGES, true)) {
            $where[] = 'c.etapa_comercial = :etapa';
            $params['etapa'] = $stage;
        }

        $advisor = (int) ($_GET['asesor'] ?? 0);
        if ($advisor > 0) {
            $where[] = 'c.id_asesor = :asesor';
            $params['asesor'] = $advisor;
        }

        $tag = (int) ($_GET['etiqueta'] ?? 0);
        if ($tag > 0) {
            $where[] = 'EXISTS (SELECT 1 FROM contacto_etiquetas cef WHERE cef.id_contacto = c.id_contacto AND cef.id_etiqueta = :etiqueta)';
            $params['etiqueta'] = $tag;
        }

        $from = trim((string) ($_GET['desde'] ?? ''));
        if ($from !== '') {
            $where[] = 'DATE(c.creado_en) >= :desde';
            $params['desde'] = $from;
        }
        $to = trim((string) ($_GET['hasta'] ?? ''));
        if ($to !== '') {
            $where[] = 'DATE(c.creado_en) <= :hasta';
            $params['hasta'] = $to;
        }

        $whereSql = implode(' AND ', $where);
        $count = $pdo->prepare("SELECT COUNT(*) FROM contactos c WHERE {$whereSql}");
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $sortMap = [
            'reciente' => 'COALESCE(c.ultima_interaccion_en, c.creado_en) DESC',
            'antiguo' => 'c.creado_en ASC',
            'nombre' => 'c.nombre_completo ASC',
            'seguimiento' => 'c.proximo_seguimiento ASC',
        ];
        $order = $sortMap[(string) ($_GET['orden'] ?? 'reciente')] ?? $sortMap['reciente'];

        $sql = "SELECT c.*, CONCAT_WS(' ', u.nombre, u.apellidos) asesor,
                    (SELECT GROUP_CONCAT(CONCAT(e.id_etiqueta, '|', e.nombre, '|', e.color_fondo, '|', e.color_texto) SEPARATOR ';;')
                     FROM contacto_etiquetas ce JOIN etiquetas e ON e.id_etiqueta = ce.id_etiqueta
                     WHERE ce.id_contacto = c.id_contacto) etiquetas_raw,
                    (SELECT COUNT(*) FROM interacciones i WHERE i.id_contacto = c.id_contacto) total_interacciones,
                    (SELECT COUNT(*) FROM cotizaciones co WHERE co.id_contacto = c.id_contacto) total_cotizaciones
                FROM contactos c
                LEFT JOIN usuarios_sistema u ON u.id_usuario = c.id_asesor
                WHERE {$whereSql}
                ORDER BY {$order}
                LIMIT {$limit} OFFSET {$offset}";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $row['id_contacto'] = (int) $row['id_contacto'];
            $row['id_asesor'] = $row['id_asesor'] !== null ? (int) $row['id_asesor'] : null;
            $row['total_interacciones'] = (int) $row['total_interacciones'];
            $row['total_cotizaciones'] = (int) $row['total_cotizaciones'];
            $row['etiquetas'] = self::parseTags($row['etiquetas_raw'] ?? null);
            unset($row['etiquetas_raw']);
        }

        Response::success([
            'items' => $rows,
            'paginacion' => [
                'pagina' => $page,
                'limite' => $limit,
                'total' => $total,
                'paginas' => max(1, (int) ceil($total / $limit)),
            ],
        ]);
    }

    public static function show(int $id): never
    {
        Auth::requireAuth();
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            "SELECT c.*, CONCAT_WS(' ', u.nombre, u.apellidos) asesor, u.correo asesor_correo
             FROM contactos c LEFT JOIN usuarios_sistema u ON u.id_usuario = c.id_asesor
             WHERE c.id_contacto = :id LIMIT 1"
        );
        $stmt->execute(['id' => $id]);
        $contact = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$contact) {
            Response::error('El contacto no existe.', 404);
        }

        $tags = $pdo->prepare(
            'SELECT e.id_etiqueta, e.nombre, e.slug, e.color_fondo, e.color_texto
             FROM contacto_etiquetas ce JOIN etiquetas e ON e.id_etiqueta = ce.id_etiqueta
             WHERE ce.id_contacto = :id ORDER BY e.nombre'
        );
        $tags->execute(['id' => $id]);

        $notes = $pdo->prepare(
            "SELECT n.id_nota, n.nota, n.es_privada, n.creada_en, n.actualizada_en,
                    CONCAT_WS(' ', u.nombre, u.apellidos) autor
             FROM notas_contacto n LEFT JOIN usuarios_sistema u ON u.id_usuario = n.id_usuario
             WHERE n.id_contacto = :id ORDER BY n.creada_en DESC LIMIT 50"
        );
        $notes->execute(['id' => $id]);

        $interactions = $pdo->prepare(
            'SELECT id_interaccion, direccion, tipo_mensaje, paso_flujo, contenido, estado_envio, registrado_en
             FROM interacciones WHERE id_contacto = :id ORDER BY registrado_en DESC LIMIT 100'
        );
        $interactions->execute(['id' => $id]);

        $quotes = $pdo->prepare(
            "SELECT co.*,
                    (SELECT GROUP_CONCAT(CONCAT(cd.producto_nombre, ' × ', cd.cantidad) SEPARATOR ', ')
                     FROM cotizacion_detalles cd WHERE cd.id_cotizacion = co.id_cotizacion) productos
             FROM cotizaciones co WHERE co.id_contacto = :id ORDER BY co.solicitada_en DESC"
        );
        $quotes->execute(['id' => $id]);

        $requests = $pdo->prepare(
            'SELECT * FROM solicitudes WHERE id_contacto = :id ORDER BY creada_en DESC LIMIT 50'
        );
        $requests->execute(['id' => $id]);

        $history = $pdo->prepare(
            "SELECT h.*, CONCAT_WS(' ', u.nombre, u.apellidos) usuario
             FROM historial_estados h LEFT JOIN usuarios_sistema u ON u.id_usuario = h.id_usuario
             WHERE h.id_contacto = :id ORDER BY h.creado_en DESC LIMIT 50"
        );
        $history->execute(['id' => $id]);

        Response::success([
            'contacto' => $contact,
            'etiquetas' => $tags->fetchAll(PDO::FETCH_ASSOC),
            'notas' => $notes->fetchAll(PDO::FETCH_ASSOC),
            'interacciones' => $interactions->fetchAll(PDO::FETCH_ASSOC),
            'cotizaciones' => $quotes->fetchAll(PDO::FETCH_ASSOC),
            'solicitudes' => $requests->fetchAll(PDO::FETCH_ASSOC),
            'historial' => $history->fetchAll(PDO::FETCH_ASSOC),
        ]);
    }

    public static function update(int $id): never
    {
        $user = Auth::requireRoles(['superadministrador', 'administrador', 'asesor']);
        $data = Request::json();
        $pdo = Database::connection();

        $currentStmt = $pdo->prepare('SELECT * FROM contactos WHERE id_contacto = :id LIMIT 1');
        $currentStmt->execute(['id' => $id]);
        $before = $currentStmt->fetch(PDO::FETCH_ASSOC);
        if (!$before) {
            Response::error('El contacto no existe.', 404);
        }

        $allowed = [
            'nombre_completo' => null,
            'correo' => null,
            'empresa' => null,
            'id_asesor' => null,
            'proximo_seguimiento' => null,
            'notas_generales' => null,
        ];
        $sets = [];
        $params = ['id' => $id];
        foreach ($allowed as $field => $_) {
            if (array_key_exists($field, $data)) {
                $sets[] = "{$field} = :{$field}";
                $value = $data[$field];
                if ($field === 'id_asesor') {
                    $value = $value === '' || $value === null ? null : (int) $value;
                } elseif (is_string($value)) {
                    $value = trim($value);
                    $value = $value === '' ? null : $value;
                }
                $params[$field] = $value;
            }
        }

        if (!$sets) {
            Response::error('No se recibieron campos para actualizar.', 422);
        }

        $stmt = $pdo->prepare('UPDATE contactos SET ' . implode(', ', $sets) . ' WHERE id_contacto = :id');
        $stmt->execute($params);
        Audit::log('actualizar', 'contacto', $id, 'Datos generales actualizados', $before, $data, (int) $user['id_usuario']);
        Response::success(null, 200, 'Contacto actualizado correctamente.');
    }

    public static function classify(int $id): never
    {
        $user = Auth::requireRoles(['superadministrador', 'administrador', 'asesor']);
        $data = Request::json();
        $type = (string) ($data['tipo_contacto'] ?? '');
        $stage = (string) ($data['etapa_comercial'] ?? '');
        $reason = trim((string) ($data['motivo'] ?? ''));

        if (!in_array($type, self::TYPES, true) || !in_array($stage, self::STAGES, true)) {
            Response::error('La clasificación o la etapa no son válidas.', 422);
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT * FROM contactos WHERE id_contacto = :id FOR UPDATE');
            $stmt->execute(['id' => $id]);
            $before = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$before) {
                $pdo->rollBack();
                Response::error('El contacto no existe.', 404);
            }

            $converted = $type === 'cliente' ? 'COALESCE(convertido_cliente_en, NOW())' : 'convertido_cliente_en';
            $update = $pdo->prepare(
                "UPDATE contactos SET tipo_contacto = :tipo, etapa_comercial = :etapa,
                 motivo_clasificacion = :motivo, convertido_cliente_en = {$converted}
                 WHERE id_contacto = :id"
            );
            $update->execute([
                'tipo' => $type,
                'etapa' => $stage,
                'motivo' => $reason !== '' ? $reason : null,
                'id' => $id,
            ]);

            $history = $pdo->prepare(
                'INSERT INTO historial_estados
                 (id_contacto, id_usuario, tipo_anterior, tipo_nuevo, etapa_anterior, etapa_nueva, motivo, origen_cambio)
                 VALUES (:contacto, :usuario, :tipo_anterior, :tipo_nuevo, :etapa_anterior, :etapa_nueva, :motivo, \'dashboard\')'
            );
            $history->execute([
                'contacto' => $id,
                'usuario' => $user['id_usuario'],
                'tipo_anterior' => $before['tipo_contacto'],
                'tipo_nuevo' => $type,
                'etapa_anterior' => $before['etapa_comercial'],
                'etapa_nueva' => $stage,
                'motivo' => $reason !== '' ? $reason : null,
            ]);
            $pdo->commit();
            Audit::log('clasificar', 'contacto', $id, 'Clasificación comercial actualizada', $before, $data, (int) $user['id_usuario']);
            Response::success(null, 200, 'Clasificación actualizada correctamente.');
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function addNote(int $id): never
    {
        $user = Auth::requireRoles(['superadministrador', 'administrador', 'asesor']);
        $data = Request::json();
        $note = trim((string) ($data['nota'] ?? ''));
        if ($note === '') {
            Response::error('Escribe una nota antes de guardarla.', 422);
        }

        $stmt = Database::connection()->prepare(
            'INSERT INTO notas_contacto (id_contacto, id_usuario, nota, es_privada)
             VALUES (:contacto, :usuario, :nota, :privada)'
        );
        $stmt->execute([
            'contacto' => $id,
            'usuario' => $user['id_usuario'],
            'nota' => $note,
            'privada' => !empty($data['es_privada']) ? 1 : 0,
        ]);
        $noteId = (int) Database::connection()->lastInsertId();
        Audit::log('crear', 'nota_contacto', $noteId, 'Nota agregada al contacto', null, ['id_contacto' => $id]);
        Response::success(['id_nota' => $noteId], 201, 'Nota guardada correctamente.');
    }

    public static function addTag(int $id): never
    {
        $user = Auth::requireRoles(['superadministrador', 'administrador', 'asesor']);
        $tagId = (int) (Request::json()['id_etiqueta'] ?? 0);
        if ($tagId <= 0) {
            Response::error('Selecciona una etiqueta válida.', 422);
        }
        $stmt = Database::connection()->prepare(
            'INSERT IGNORE INTO contacto_etiquetas (id_contacto, id_etiqueta, id_asignado_por)
             VALUES (:contacto, :etiqueta, :usuario)'
        );
        $stmt->execute(['contacto' => $id, 'etiqueta' => $tagId, 'usuario' => $user['id_usuario']]);
        Audit::log('asignar_etiqueta', 'contacto', $id, 'Etiqueta asignada', null, ['id_etiqueta' => $tagId]);
        Response::success(null, 201, 'Etiqueta asignada.');
    }

    public static function removeTag(int $id, int $tagId): never
    {
        Auth::requireRoles(['superadministrador', 'administrador', 'asesor']);
        $stmt = Database::connection()->prepare(
            'DELETE FROM contacto_etiquetas WHERE id_contacto = :contacto AND id_etiqueta = :etiqueta'
        );
        $stmt->execute(['contacto' => $id, 'etiqueta' => $tagId]);
        Audit::log('quitar_etiqueta', 'contacto', $id, 'Etiqueta eliminada', ['id_etiqueta' => $tagId], null);
        Response::success(null, 200, 'Etiqueta eliminada.');
    }

    private static function parseTags(?string $raw): array
    {
        if (!$raw) {
            return [];
        }
        $tags = [];
        foreach (explode(';;', $raw) as $item) {
            $parts = explode('|', $item);
            if (count($parts) === 4) {
                $tags[] = [
                    'id_etiqueta' => (int) $parts[0],
                    'nombre' => $parts[1],
                    'color_fondo' => $parts[2],
                    'color_texto' => $parts[3],
                ];
            }
        }
        return $tags;
    }
}
