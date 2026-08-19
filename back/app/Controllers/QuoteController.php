<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use PDO;

final class QuoteController
{
    private const STATES = ['solicitada', 'en_revision', 'enviada', 'aceptada', 'rechazada', 'vencida', 'cancelada'];
    private const FILE_EXTENSIONS = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'png', 'jpg', 'jpeg'];
    private const MAX_FILE_SIZE = 10485760; // 10 MB

    public static function index(): never
    {
        Auth::requireAuth();
        $pdo = Database::connection();
        self::ensureFileTable($pdo);
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = min(100, max(5, (int) ($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;
        $where = ['1=1'];
        $params = [];

        $state = (string) ($_GET['estado'] ?? '');
        if (in_array($state, self::STATES, true)) {
            $where[] = 'co.estado = :estado';
            $params['estado'] = $state;
        }
        $q = trim((string) ($_GET['q'] ?? ''));
        if ($q !== '') {
            // Evita HY093 con PDO::ATTR_EMULATE_PREPARES=false.
            $where[] = '(co.folio LIKE :q_folio OR c.nombre_completo LIKE :q_nombre OR c.whatsapp LIKE :q_whatsapp)';
            $like = '%' . $q . '%';
            $params['q_folio'] = $like;
            $params['q_nombre'] = $like;
            $params['q_whatsapp'] = $like;
        }
        $whereSql = implode(' AND ', $where);

        $count = $pdo->prepare("SELECT COUNT(*) FROM cotizaciones co JOIN contactos c ON c.id_contacto = co.id_contacto WHERE {$whereSql}");
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $stmt = $pdo->prepare(
            "SELECT co.*, c.nombre_completo, c.whatsapp, c.tipo_contacto,
                    CONCAT_WS(' ', u.nombre, u.apellidos) asesor,
                    (SELECT GROUP_CONCAT(CONCAT(cd.producto_nombre, ' × ', cd.cantidad) SEPARATOR ', ')
                     FROM cotizacion_detalles cd WHERE cd.id_cotizacion = co.id_cotizacion) productos,
                    (SELECT ca.archivo_url FROM cotizacion_archivos ca WHERE ca.id_cotizacion = co.id_cotizacion AND ca.activo = 1 ORDER BY ca.subido_en DESC LIMIT 1) archivo_url,
                    (SELECT ca.archivo_nombre_original FROM cotizacion_archivos ca WHERE ca.id_cotizacion = co.id_cotizacion AND ca.activo = 1 ORDER BY ca.subido_en DESC LIMIT 1) archivo_nombre_original,
                    (SELECT ca.subido_en FROM cotizacion_archivos ca WHERE ca.id_cotizacion = co.id_cotizacion AND ca.activo = 1 ORDER BY ca.subido_en DESC LIMIT 1) archivo_subido_en
             FROM cotizaciones co
             JOIN contactos c ON c.id_contacto = co.id_contacto
             LEFT JOIN usuarios_sistema u ON u.id_usuario = co.id_asesor
             WHERE {$whereSql}
             ORDER BY co.solicitada_en DESC
             LIMIT {$limit} OFFSET {$offset}"
        );
        $stmt->execute($params);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        Response::success([
            'items' => $items,
            'paginacion' => [
                'pagina' => $page,
                'limite' => $limit,
                'total' => $total,
                'paginas' => max(1, (int) ceil($total / $limit)),
            ],
        ]);
    }

    public static function conversation(int $id): never
    {
        Auth::requireAuth();
        $pdo = Database::connection();
        self::ensureFileTable($pdo);

        $stmt = $pdo->prepare(
            "SELECT co.*, c.nombre_completo, c.whatsapp, c.correo, c.empresa,
                    cv.rama conversacion_rama, cv.estado conversacion_estado,
                    cv.resultado conversacion_resultado, cv.iniciada_en conversacion_iniciada_en,
                    cv.finalizada_en conversacion_finalizada_en,
                    (SELECT ca.archivo_url FROM cotizacion_archivos ca
                     WHERE ca.id_cotizacion = co.id_cotizacion AND ca.activo = 1
                     ORDER BY ca.subido_en DESC LIMIT 1) archivo_url,
                    (SELECT ca.archivo_nombre_original FROM cotizacion_archivos ca
                     WHERE ca.id_cotizacion = co.id_cotizacion AND ca.activo = 1
                     ORDER BY ca.subido_en DESC LIMIT 1) archivo_nombre_original
             FROM cotizaciones co
             JOIN contactos c ON c.id_contacto = co.id_contacto
             LEFT JOIN conversaciones cv ON cv.id_conversacion = co.id_conversacion
             WHERE co.id_cotizacion = :id LIMIT 1"
        );
        $stmt->execute(['id' => $id]);
        $quote = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$quote) {
            Response::error('La cotización no existe.', 404);
        }

        if (!empty($quote['id_conversacion'])) {
            $interactions = $pdo->prepare(
                'SELECT id_interaccion, direccion, tipo_mensaje, paso_flujo, contenido, estado_envio, registrado_en
                 FROM interacciones WHERE id_conversacion = :conversacion ORDER BY registrado_en ASC'
            );
            $interactions->execute(['conversacion' => (int) $quote['id_conversacion']]);
        } else {
            // Respaldo para registros históricos sin id_conversacion asociado.
            $interactions = $pdo->prepare(
                'SELECT id_interaccion, direccion, tipo_mensaje, paso_flujo, contenido, estado_envio, registrado_en
                 FROM interacciones WHERE id_contacto = :contacto ORDER BY registrado_en DESC LIMIT 100'
            );
            $interactions->execute(['contacto' => (int) $quote['id_contacto']]);
        }

        Response::success([
            'cotizacion' => $quote,
            'interacciones' => $interactions->fetchAll(PDO::FETCH_ASSOC),
        ]);
    }

    public static function createForContact(int $contactId): never
    {
        $user = Auth::requireRoles(['superadministrador', 'administrador', 'asesor']);
        $pdo = Database::connection();
        self::ensureFileTable($pdo);

        $contactStmt = $pdo->prepare('SELECT id_contacto, tipo_contacto FROM contactos WHERE id_contacto = :id AND activo = 1 LIMIT 1');
        $contactStmt->execute(['id' => $contactId]);
        $contact = $contactStmt->fetch(PDO::FETCH_ASSOC);
        if (!$contact) {
            Response::error('El contacto no existe.', 404);
        }

        if (empty($_FILES['archivo']) || !is_array($_FILES['archivo'])) {
            Response::error('Selecciona el archivo de la cotización.', 422);
        }

        $file = $_FILES['archivo'];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            Response::error('No se pudo cargar el archivo. Inténtalo nuevamente.', 422);
        }

        $originalName = (string) ($file['name'] ?? 'cotizacion');
        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > self::MAX_FILE_SIZE) {
            Response::error('El archivo debe pesar máximo 10 MB.', 422);
        }

        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($extension, self::FILE_EXTENSIONS, true)) {
            Response::error('Formato no permitido. Usa PDF, Word, Excel o imagen.', 422);
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        $mime = self::detectMime($tmp);
        $folio = 'COT-MAN-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $type = ($contact['tipo_contacto'] ?? '') === 'cliente' ? 'cliente_existente' : 'primera_compra';

        $storageDir = dirname(__DIR__, 2) . '/storage/cotizaciones';
        if (!is_dir($storageDir) && !mkdir($storageDir, 0755, true) && !is_dir($storageDir)) {
            Response::error('No se pudo preparar la carpeta de archivos.', 500);
        }
        $htaccess = $storageDir . '/.htaccess';
        if (!is_file($htaccess)) {
            file_put_contents($htaccess, "Options -Indexes\n<FilesMatch \"\\.(php|phtml|phar)$\">\n    Require all denied\n</FilesMatch>\n");
        }

        $pdo->beginTransaction();
        $target = null;
        try {
            $insertQuote = $pdo->prepare(
                "INSERT INTO cotizaciones
                 (folio, id_contacto, tipo_cliente_declarado, subtotal, total, estado, id_asesor, notas)
                 VALUES (:folio, :contacto, :tipo, 0, 0, 'en_revision', :asesor, :notas)"
            );
            $insertQuote->execute([
                'folio' => $folio,
                'contacto' => $contactId,
                'tipo' => $type,
                'asesor' => $user['id_usuario'],
                'notas' => 'Cotización adjuntada manualmente desde el CRM.',
            ]);
            $quoteId = (int) $pdo->lastInsertId();

            $safeName = 'cotizacion_' . $quoteId . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
            $target = $storageDir . '/' . $safeName;
            if (!move_uploaded_file($tmp, $target)) {
                throw new \RuntimeException('No se pudo guardar el archivo adjunto.');
            }
            @chmod($target, 0644);

            $basePath = (string) ($GLOBALS['app_config']['base_path'] ?? '/ccdeqbot/back/api');
            $storageUrlBase = preg_replace('#/api/?$#', '/storage/cotizaciones', $basePath) ?: '/ccdeqbot/back/storage/cotizaciones';
            $fileUrl = rtrim($storageUrlBase, '/') . '/' . rawurlencode($safeName);

            $insertFile = $pdo->prepare(
                'INSERT INTO cotizacion_archivos
                 (id_cotizacion, archivo_nombre_original, archivo_nombre_guardado, archivo_url, mime_type, tamano_bytes, subido_por)
                 VALUES (:cotizacion, :original, :guardado, :url, :mime, :tamano, :usuario)'
            );
            $insertFile->execute([
                'cotizacion' => $quoteId,
                'original' => mb_substr($originalName, 0, 255),
                'guardado' => $safeName,
                'url' => $fileUrl,
                'mime' => mb_substr($mime, 0, 120),
                'tamano' => $size,
                'usuario' => $user['id_usuario'],
            ]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($target && is_file($target)) {
                @unlink($target);
            }
            throw $e;
        }

        Audit::log(
            'crear_manual',
            'cotizacion',
            $quoteId,
            'Cotización manual adjuntada al contacto',
            null,
            ['id_contacto' => $contactId, 'archivo' => $originalName],
            (int) $user['id_usuario']
        );

        Response::success([
            'id_cotizacion' => $quoteId,
            'folio' => $folio,
            'archivo_url' => $fileUrl,
            'archivo_nombre_original' => $originalName,
        ], 201, 'Cotización adjuntada correctamente.');
    }

    public static function updateState(int $id): never
    {
        $user = Auth::requireRoles(['superadministrador', 'administrador', 'asesor']);
        $data = Request::json();
        $state = (string) ($data['estado'] ?? '');
        if (!in_array($state, self::STATES, true)) {
            Response::error('El estado de la cotización no es válido.', 422);
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM cotizaciones WHERE id_cotizacion = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $before = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$before) {
            Response::error('La cotización no existe.', 404);
        }

        $extra = '';
        if ($state === 'enviada') {
            $extra = ', enviada_en = COALESCE(enviada_en, NOW())';
        } elseif ($state === 'aceptada') {
            $extra = ', aceptada_en = COALESCE(aceptada_en, NOW())';
        }

        $update = $pdo->prepare("UPDATE cotizaciones SET estado = :estado {$extra} WHERE id_cotizacion = :id");
        $update->execute(['estado' => $state, 'id' => $id]);
        Audit::log('cambiar_estado', 'cotizacion', $id, 'Estado de cotización actualizado', $before, ['estado' => $state], (int) $user['id_usuario']);
        Response::success(null, 200, 'Estado actualizado correctamente.');
    }

    public static function uploadFile(int $id): never
    {
        $user = Auth::requireRoles(['superadministrador', 'administrador', 'asesor']);
        $pdo = Database::connection();
        self::ensureFileTable($pdo);

        $stmt = $pdo->prepare('SELECT * FROM cotizaciones WHERE id_cotizacion = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $quote = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$quote) {
            Response::error('La cotización no existe.', 404);
        }

        if (empty($_FILES['archivo']) || !is_array($_FILES['archivo'])) {
            Response::error('Adjunta un archivo para la cotización.', 422);
        }

        $file = $_FILES['archivo'];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            Response::error('No se pudo cargar el archivo. Inténtalo nuevamente.', 422);
        }

        $originalName = (string) ($file['name'] ?? 'cotizacion');
        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > self::MAX_FILE_SIZE) {
            Response::error('El archivo debe pesar máximo 10 MB.', 422);
        }

        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($extension, self::FILE_EXTENSIONS, true)) {
            Response::error('Formato no permitido. Usa PDF, Word, Excel o imagen.', 422);
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        $mime = self::detectMime($tmp);
        $safeName = 'cotizacion_' . $id . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
        $storageDir = dirname(__DIR__, 2) . '/storage/cotizaciones';
        if (!is_dir($storageDir) && !mkdir($storageDir, 0755, true) && !is_dir($storageDir)) {
            Response::error('No se pudo preparar la carpeta de archivos.', 500);
        }

        $htaccess = $storageDir . '/.htaccess';
        if (!is_file($htaccess)) {
            file_put_contents($htaccess, "Options -Indexes\n<FilesMatch \"\\.(php|phtml|phar)$\">\n    Require all denied\n</FilesMatch>\n");
        }

        $target = $storageDir . '/' . $safeName;
        if (!move_uploaded_file($tmp, $target)) {
            Response::error('No se pudo guardar el archivo adjunto.', 500);
        }
        @chmod($target, 0644);

        $basePath = (string) ($GLOBALS['app_config']['base_path'] ?? '/ccdeqbot/back/api');
        $storageUrlBase = preg_replace('#/api/?$#', '/storage/cotizaciones', $basePath) ?: '/ccdeqbot/back/storage/cotizaciones';
        $fileUrl = rtrim($storageUrlBase, '/') . '/' . rawurlencode($safeName);

        $pdo->beginTransaction();
        try {
            $deactivate = $pdo->prepare('UPDATE cotizacion_archivos SET activo = 0 WHERE id_cotizacion = :id');
            $deactivate->execute(['id' => $id]);

            $insert = $pdo->prepare(
                'INSERT INTO cotizacion_archivos
                 (id_cotizacion, archivo_nombre_original, archivo_nombre_guardado, archivo_url, mime_type, tamano_bytes, subido_por)
                 VALUES (:cotizacion, :original, :guardado, :url, :mime, :tamano, :usuario)'
            );
            $insert->execute([
                'cotizacion' => $id,
                'original' => mb_substr($originalName, 0, 255),
                'guardado' => $safeName,
                'url' => $fileUrl,
                'mime' => mb_substr($mime, 0, 120),
                'tamano' => $size,
                'usuario' => $user['id_usuario'],
            ]);
            $fileId = (int) $pdo->lastInsertId();
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            @unlink($target);
            throw $e;
        }

        Audit::log('adjuntar_archivo', 'cotizacion', $id, 'Archivo adjuntado a cotización', null, ['archivo' => $originalName], (int) $user['id_usuario']);
        Response::success([
            'id_archivo' => $fileId,
            'archivo_url' => $fileUrl,
            'archivo_nombre_original' => $originalName,
        ], 201, 'Archivo adjuntado correctamente.');
    }

    public static function ensureFileTable(PDO $pdo): void
    {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS cotizacion_archivos (
                id_archivo INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                id_cotizacion BIGINT UNSIGNED NOT NULL,
                archivo_nombre_original VARCHAR(255) NOT NULL,
                archivo_nombre_guardado VARCHAR(255) NOT NULL,
                archivo_url VARCHAR(500) NOT NULL,
                mime_type VARCHAR(120) NULL,
                tamano_bytes INT UNSIGNED NOT NULL DEFAULT 0,
                subido_por INT NULL,
                subido_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                activo TINYINT(1) NOT NULL DEFAULT 1,
                INDEX idx_cotizacion_activo (id_cotizacion, activo),
                INDEX idx_subido_en (subido_en)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private static function detectMime(string $tmp): string
    {
        if ($tmp !== '' && is_file($tmp) && function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mime = finfo_file($finfo, $tmp) ?: 'application/octet-stream';
                finfo_close($finfo);
                return $mime;
            }
        }
        return 'application/octet-stream';
    }
}
