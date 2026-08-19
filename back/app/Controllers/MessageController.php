<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Response;
use PDO;

final class MessageController
{
    private const SEGMENTS = ['lead', 'lead_recurrente', 'cliente', 'cliente_recurrente'];
    private const MAX_RECIPIENTS = 200;
    private const MAX_IMAGE_BYTES = 4 * 1024 * 1024;

    /**
     * Segmentación comercial derivada del comportamiento real del CRM.
     * - Prospecto: primer ciclo de conversación y aún no es cliente.
     * - Prospecto recurrente: vuelve a iniciar una conversación sin haberse convertido en cliente.
     * - Cliente: cliente validado o declarado, sin una recompra posterior detectada.
     * - Cliente recurrente: cliente validado que volvió después de convertirse o acumula 2+ cotizaciones aceptadas.
     */
    private static function segmentExpression(string $alias = 'c'): string
    {
        return "CASE
            WHEN {$alias}.tipo_contacto = 'cliente' AND (
                (SELECT COUNT(*) FROM cotizaciones mcq
                 WHERE mcq.id_contacto = {$alias}.id_contacto AND mcq.estado = 'aceptada') >= 2
                OR (
                    {$alias}.convertido_cliente_en IS NOT NULL
                    AND EXISTS (
                        SELECT 1 FROM conversaciones mcv
                        WHERE mcv.id_contacto = {$alias}.id_contacto
                          AND mcv.iniciada_en > {$alias}.convertido_cliente_en
                        LIMIT 1
                    )
                )
            ) THEN 'cliente_recurrente'
            WHEN {$alias}.tipo_contacto IN ('cliente', 'cliente_pendiente') THEN 'cliente'
            WHEN {$alias}.tipo_contacto = 'lead' AND (
                SELECT COUNT(*) FROM conversaciones mlv
                WHERE mlv.id_contacto = {$alias}.id_contacto
            ) > 1 THEN 'lead_recurrente'
            ELSE 'lead'
        END";
    }

    public static function summary(): never
    {
        Auth::requireAuth();
        $pdo = Database::connection();
        $segment = self::segmentExpression('c');

        $rows = $pdo->query(
            "SELECT segmento, COUNT(*) total
             FROM (
                 SELECT c.id_contacto, {$segment} segmento
                 FROM contactos c
                 WHERE c.activo = 1 AND c.whatsapp IS NOT NULL AND TRIM(c.whatsapp) <> ''
             ) s
             GROUP BY segmento"
        )->fetchAll(PDO::FETCH_ASSOC);

        $map = array_fill_keys(self::SEGMENTS, 0);
        foreach ($rows as $row) {
            $key = (string) ($row['segmento'] ?? '');
            if (array_key_exists($key, $map)) {
                $map[$key] = (int) ($row['total'] ?? 0);
            }
        }

        $items = [
            ['tipo' => 'lead', 'etiqueta' => 'Prospectos', 'total' => $map['lead']],
            ['tipo' => 'lead_recurrente', 'etiqueta' => 'Prospectos recurrentes', 'total' => $map['lead_recurrente']],
            ['tipo' => 'cliente', 'etiqueta' => 'Clientes', 'total' => $map['cliente']],
            ['tipo' => 'cliente_recurrente', 'etiqueta' => 'Clientes recurrentes', 'total' => $map['cliente_recurrente']],
        ];

        Response::success([
            'tipos' => $items,
            'total' => array_sum($map),
            'limite_por_envio' => self::MAX_RECIPIENTS,
        ]);
    }

    public static function send(): never
    {
        $user = Auth::requireRoles(['superadministrador', 'administrador', 'asesor']);

        $contactId = max(0, (int) ($_POST['contacto_id'] ?? 0));
        $types = $contactId > 0 ? [] : self::parseTypes($_POST['tipos'] ?? null);
        if ($contactId <= 0 && !$types) {
            Response::error('Selecciona un tipo de cliente, todos o un contacto específico.', 422);
        }

        $message = trim((string) ($_POST['mensaje'] ?? ''));
        if (mb_strlen($message) > 4096) {
            Response::error('El mensaje no puede superar 4096 caracteres.', 422);
        }

        $image = self::readImage();
        if ($message === '' && $image === null) {
            Response::error('Escribe un mensaje o adjunta una imagen.', 422);
        }

        $pdo = Database::connection();
        if ($contactId > 0) {
            $recipients = self::recipientById($pdo, $contactId);
            if (!$recipients) {
                Response::error('El contacto seleccionado no existe, está inactivo o no tiene número de WhatsApp.', 422);
            }
            $types = [(string) $recipients[0]['segmento']];
        } else {
            $recipients = self::recipients($pdo, $types);
            if (!$recipients) {
                Response::error('No hay contactos con WhatsApp dentro del tipo de cliente seleccionado.', 422);
            }
        }
        if (count($recipients) > self::MAX_RECIPIENTS) {
            Response::error(
                'El envío supera el límite de ' . self::MAX_RECIPIENTS . ' contactos. Selecciona menos tipos o divide el envío.',
                422
            );
        }

        $isIndividual = $contactId > 0;
        $config = $GLOBALS['app_config']['messaging'] ?? [];

        // Opción A: la web encola la promoción. Firebase decide por destinatario:
        // mensaje libre si la ventana de 24 h está abierta; template MARKETING aprobado si está cerrada.
        if (($config['use_scheduled_queue'] ?? false) === true) {
            self::queueSend($pdo, $recipients, $types, $message, $image, $user, $isIndividual, $contactId);
        }

        $url = trim((string) ($isIndividual
            ? ($config['firebase_agent_url'] ?? '')
            : ($config['firebase_broadcast_url'] ?? '')));
        $token = trim((string) ($isIndividual
            ? ($config['agent_token'] ?? $config['broadcast_token'] ?? '')
            : ($config['broadcast_token'] ?? '')));

        if ($url === '' || $token === '') {
            Response::error(
                $isIndividual
                    ? 'El servicio de mensajes individuales del bot aún no está configurado en el CRM.'
                    : 'El servicio de mensajería del bot aún no está configurado en el CRM.',
                503
            );
        }
        if (!function_exists('curl_init')) {
            Response::error('El servidor necesita la extensión cURL de PHP para conectarse con el bot.', 500);
        }

        if ($isIndividual) {
            $recipient = $recipients[0];
            $agentName = trim((string) ($user['nombre'] ?? '') . ' ' . (string) ($user['apellidos'] ?? ''));
            $payload = [
                'contactId' => (int) $recipient['id_contacto'],
                'phone' => (string) $recipient['whatsapp'],
                'text' => $message,
                'agentId' => (int) $user['id_usuario'],
                'agentName' => $agentName !== '' ? $agentName : 'Usuario CRM',
                'tipo' => (string) $recipient['segmento'],
            ];
        } else {
            $payload = [
                'mensaje' => $message,
                'tipos' => $types,
                'destinatarios' => array_map(static fn(array $row): array => [
                    'id_contacto' => (int) $row['id_contacto'],
                    'whatsapp' => (string) $row['whatsapp'],
                    'nombre' => $row['nombre_completo'] ?: null,
                    'tipo' => (string) $row['segmento'],
                ], $recipients),
            ];
        }

        if ($image !== null) {
            $payload['imagen'] = $image;
        }

        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            Response::error('No fue posible preparar el envío.', 500);
        }

        $tokenHeader = $isIndividual ? 'X-Agent-Token: ' : 'X-CRM-Token: ';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                $tokenHeader . $token,
            ],
            CURLOPT_POSTFIELDS => $encoded,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 180,
        ]);
        $raw = curl_exec($ch);
        $curlError = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false) {
            Response::error('No fue posible conectar con el bot: ' . ($curlError ?: 'error de conexión'), 502);
        }

        $result = json_decode((string) $raw, true);
        if (!is_array($result)) {
            Response::error('El bot devolvió una respuesta no válida.', 502);
        }
        $completed = ($result['success'] ?? false) === true || ($result['ok'] ?? false) === true;
        if ($status < 200 || $status >= 300 || !$completed) {
            $messageError = trim((string) ($result['message'] ?? ''));
            $detail = trim((string) ($result['detail'] ?? ''));
            if ($detail !== '') {
                $messageError = $messageError !== '' ? $messageError . ' (' . $detail . ')' : $detail;
            }
            Response::error($messageError !== '' ? $messageError : 'El bot no pudo completar el envío.', 502);
        }

        $sent = (int) ($result['enviados'] ?? ($isIndividual ? 1 : 0));
        $errors = (int) ($result['errores'] ?? 0);
        $requested = (int) ($result['solicitados'] ?? count($recipients));

        Audit::log(
            $isIndividual ? 'enviar_mensaje_individual' : 'enviar_mensaje_masivo',
            'contactos',
            $isIndividual ? $contactId : null,
            $isIndividual
                ? 'Mensaje individual enviado desde el módulo Mensajes'
                : 'Mensaje por grupo enviado desde el módulo Mensajes',
            null,
            [
                'tipos' => $types,
                'contacto_individual' => $isIndividual ? $contactId : null,
                'destinatarios' => $requested,
                'con_imagen' => $image !== null,
                'enviados' => $sent,
                'errores' => $errors,
                'canal' => $isIndividual ? 'agente_individual' : 'broadcast',
            ],
            (int) $user['id_usuario']
        );

        Response::success([
            'solicitados' => $requested,
            'enviados' => $sent,
            'errores' => $errors,
            'modo_envio' => $isIndividual ? 'individual' : 'grupo',
            'meta_message_ids' => (array) ($result['meta_message_ids'] ?? []),
            'detalle_errores' => array_slice((array) ($result['detalle_errores'] ?? []), 0, 20),
        ], 200, $isIndividual ? 'Mensaje individual enviado a Meta.' : 'Proceso de envío por grupo finalizado.');
    }


    private static function queueSend(
        PDO $pdo,
        array $recipients,
        array $types,
        string $message,
        ?array $image,
        array $user,
        bool $isIndividual,
        int $contactId
    ): never {
        $segmentsJson = json_encode(array_values($types), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($segmentsJson === false) {
            Response::error('No fue posible preparar la segmentación del mensaje.', 500);
        }

        try {
            $pdo->beginTransaction();

            $campaignStmt = $pdo->prepare(
                "INSERT INTO mensajes_campanas
                    (mensaje, imagen_nombre, imagen_mime, imagen_base64, segmentos_json,
                     creado_por, estado, total_destinatarios, enviados, errores, creado_en, actualizado_en)
                 VALUES
                    (:mensaje, :imagen_nombre, :imagen_mime, :imagen_base64, :segmentos_json,
                     :creado_por, 'pendiente', :total, 0, 0, NOW(), NOW())"
            );
            $campaignStmt->execute([
                'mensaje' => $message !== '' ? $message : null,
                'imagen_nombre' => $image['nombre'] ?? null,
                'imagen_mime' => $image['mime'] ?? null,
                'imagen_base64' => $image['base64'] ?? null,
                'segmentos_json' => $segmentsJson,
                'creado_por' => (int) ($user['id_usuario'] ?? 0) ?: null,
                'total' => count($recipients),
            ]);
            $campaignId = (int) $pdo->lastInsertId();

            $sendStmt = $pdo->prepare(
                "INSERT INTO mensajes_envios
                    (id_campana, id_contacto, whatsapp, nombre, segmento, estado, intentos,
                     programado_en, actualizado_en)
                 VALUES
                    (:id_campana, :id_contacto, :whatsapp, :nombre, :segmento, 'pendiente', 0,
                     NOW(), NOW())"
            );

            foreach ($recipients as $recipient) {
                $sendStmt->execute([
                    'id_campana' => $campaignId,
                    'id_contacto' => (int) $recipient['id_contacto'],
                    'whatsapp' => (string) $recipient['whatsapp'],
                    'nombre' => ($recipient['nombre_completo'] ?? null) ?: null,
                    'segmento' => (string) ($recipient['segmento'] ?? ''),
                ]);
            }

            $pdo->commit();
        } catch (\Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $detail = $error->getMessage();
            if (stripos($detail, 'mensajes_campanas') !== false || stripos($detail, 'mensajes_envios') !== false) {
                Response::error('Falta importar database/07_cola_mensajes_colonbot.sql antes de usar la cola de mensajes.', 500);
            }
            Response::error('No fue posible guardar el mensaje en la cola de envío.', 500);
        }

        Audit::log(
            $isIndividual ? 'encolar_mensaje_individual' : 'encolar_mensaje_masivo',
            'contactos',
            $isIndividual ? $contactId : null,
            $isIndividual
                ? 'Mensaje individual agregado a la cola programada'
                : 'Mensaje por grupo agregado a la cola programada',
            null,
            [
                'campana_id' => $campaignId,
                'tipos' => $types,
                'contacto_individual' => $isIndividual ? $contactId : null,
                'destinatarios' => count($recipients),
                'con_imagen' => $image !== null,
                'canal' => 'cola_24h_template',
            ],
            (int) ($user['id_usuario'] ?? 0)
        );

        Response::success([
            'campana_id' => $campaignId,
            'solicitados' => count($recipients),
            'en_cola' => count($recipients),
            'enviados' => 0,
            'errores' => 0,
            'modo_envio' => 'cola',
            'procesamiento' => 'checkMessageSendsCCdeQbot',
        ], 200, 'Promoción agregada a la cola. Firebase usará mensaje libre dentro de 24 h y plantilla aprobada de Meta fuera de 24 h.');
    }

    private static function parseTypes(mixed $raw): array
    {
        if (is_array($raw)) {
            $types = $raw;
        } else {
            $decoded = json_decode((string) $raw, true);
            $types = is_array($decoded) ? $decoded : [];
        }

        $types = array_values(array_unique(array_filter(array_map(
            static fn(mixed $value): string => trim((string) $value),
            $types
        ))));

        if (in_array('todos', $types, true)) {
            return self::SEGMENTS;
        }

        return array_values(array_intersect(self::SEGMENTS, $types));
    }

    private static function readImage(): ?array
    {
        if (!isset($_FILES['imagen']) || !is_array($_FILES['imagen'])) {
            return null;
        }

        $file = $_FILES['imagen'];
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ($error !== UPLOAD_ERR_OK) {
            Response::error('No fue posible recibir la imagen adjunta.', 422);
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > self::MAX_IMAGE_BYTES) {
            Response::error('La imagen debe pesar máximo 4 MB.', 422);
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            Response::error('El archivo adjunto no es válido.', 422);
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($tmp);
        $allowed = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($mime, $allowed, true)) {
            Response::error('Solo se permiten imágenes JPG, PNG o WEBP.', 422);
        }

        $contents = file_get_contents($tmp);
        if ($contents === false) {
            Response::error('No fue posible leer la imagen adjunta.', 422);
        }

        $name = basename((string) ($file['name'] ?? 'imagen'));
        $name = preg_replace('/[^A-Za-z0-9._-]+/', '_', $name) ?: 'imagen';

        return [
            'nombre' => mb_substr($name, 0, 120),
            'mime' => $mime,
            'base64' => base64_encode($contents),
        ];
    }

    private static function recipientById(PDO $pdo, int $contactId): array
    {
        $segment = self::segmentExpression('c');
        $stmt = $pdo->prepare(
            "SELECT c.id_contacto, c.whatsapp, c.nombre_completo,
                    {$segment} segmento
             FROM contactos c
             WHERE c.id_contacto = :id
               AND c.activo = 1
               AND c.whatsapp IS NOT NULL
               AND TRIM(c.whatsapp) <> ''
             LIMIT 1"
        );
        $stmt->execute(['id' => $contactId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? [$row] : [];
    }

    private static function recipients(PDO $pdo, array $types): array
    {
        $segment = self::segmentExpression('c');
        $placeholders = [];
        $params = [];
        foreach (array_values($types) as $index => $type) {
            $name = ':tipo' . $index;
            $placeholders[] = $name;
            $params['tipo' . $index] = $type;
        }

        $sql = "SELECT id_contacto, whatsapp, nombre_completo, segmento
                FROM (
                    SELECT c.id_contacto, c.whatsapp, c.nombre_completo,
                           {$segment} segmento,
                           COALESCE(c.ultima_interaccion_en, c.creado_en) actividad
                    FROM contactos c
                    WHERE c.activo = 1
                      AND c.whatsapp IS NOT NULL
                      AND TRIM(c.whatsapp) <> ''
                ) destinatarios
                WHERE segmento IN (" . implode(',', $placeholders) . ")
                ORDER BY actividad DESC
                LIMIT " . (self::MAX_RECIPIENTS + 1);

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
