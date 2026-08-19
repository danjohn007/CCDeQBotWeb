<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Response;
use DateInterval;
use DateTimeImmutable;
use PDO;

final class DashboardController
{
    public static function summary(): never
    {
        Auth::requireAuth();
        $pdo = Database::connection();

        $counts = $pdo->query(
            "SELECT
                COUNT(*) total_contactos,
                SUM(tipo_contacto = 'lead') total_leads,
                SUM(tipo_contacto = 'cliente_pendiente') total_pendientes,
                SUM(tipo_contacto = 'cliente') total_clientes,
                SUM(DATE(creado_en) = CURDATE()) nuevos_hoy
             FROM contactos WHERE activo = 1"
        )->fetch(PDO::FETCH_ASSOC) ?: [];

        $quotes = $pdo->query(
            "SELECT COUNT(*) cotizaciones_abiertas, COALESCE(SUM(total), 0) monto_potencial
             FROM cotizaciones WHERE estado IN ('solicitada','en_revision','enviada')"
        )->fetch(PDO::FETCH_ASSOC) ?: [];

        $interactionsToday = (int) $pdo->query(
            "SELECT COUNT(*) FROM interacciones WHERE DATE(registrado_en) = CURDATE()"
        )->fetchColumn();

        $total = (int) ($counts['total_contactos'] ?? 0);
        $leads = (int) ($counts['total_leads'] ?? 0);
        $clients = (int) ($counts['total_clientes'] ?? 0);
        $conversionBase = max(0, $leads + $clients);
        $conversion = $conversionBase > 0 ? round(($clients / $conversionBase) * 100, 1) : 0.0;

        $attention = $pdo->query(
            "SELECT
                SUM(c.tipo_contacto = 'lead' AND (c.id_asesor IS NOT NULL OR EXISTS (
                    SELECT 1 FROM interacciones i WHERE i.id_contacto = c.id_contacto AND i.direccion = 'saliente' LIMIT 1
                ))) leads_atendidos,
                SUM(c.tipo_contacto = 'lead' AND NOT (c.id_asesor IS NOT NULL OR EXISTS (
                    SELECT 1 FROM interacciones i WHERE i.id_contacto = c.id_contacto AND i.direccion = 'saliente' LIMIT 1
                ))) leads_por_atender,
                SUM(c.tipo_contacto = 'cliente' AND (c.id_asesor IS NOT NULL OR EXISTS (
                    SELECT 1 FROM interacciones i WHERE i.id_contacto = c.id_contacto AND i.direccion = 'saliente' LIMIT 1
                ))) clientes_atendidos,
                SUM(c.tipo_contacto = 'cliente' AND NOT (c.id_asesor IS NOT NULL OR EXISTS (
                    SELECT 1 FROM interacciones i WHERE i.id_contacto = c.id_contacto AND i.direccion = 'saliente' LIMIT 1
                ))) clientes_por_atender
             FROM contactos c WHERE c.activo = 1"
        )->fetch(PDO::FETCH_ASSOC) ?: [];

        // Los cuatro tipos visibles del Customer Journey se derivan del comportamiento real
        // sin alterar el flujo conversacional del bot ni perder la clasificación histórica.
        $segmentExpression = "CASE
            WHEN c.tipo_contacto = 'cliente' AND (
                (SELECT COUNT(*) FROM cotizaciones dcq
                 WHERE dcq.id_contacto = c.id_contacto AND dcq.estado = 'aceptada') >= 2
                OR (
                    c.convertido_cliente_en IS NOT NULL
                    AND EXISTS (
                        SELECT 1 FROM conversaciones dcv
                        WHERE dcv.id_contacto = c.id_contacto
                          AND dcv.iniciada_en > c.convertido_cliente_en
                        LIMIT 1
                    )
                )
            ) THEN 'cliente_recurrente'
            WHEN c.tipo_contacto IN ('cliente', 'cliente_pendiente') THEN 'cliente'
            WHEN c.tipo_contacto = 'lead' AND (
                SELECT COUNT(*) FROM conversaciones dlv
                WHERE dlv.id_contacto = c.id_contacto
            ) > 1 THEN 'lead_recurrente'
            ELSE 'lead'
        END";

        $userTypeRows = $pdo->query(
            "SELECT segmento, COUNT(*) total
             FROM (
                 SELECT c.id_contacto, {$segmentExpression} segmento
                 FROM contactos c
                 WHERE c.activo = 1
             ) tipos
             GROUP BY segmento"
        )->fetchAll(PDO::FETCH_ASSOC);

        $userTypeMap = [
            'lead' => 0,
            'lead_recurrente' => 0,
            'cliente' => 0,
            'cliente_recurrente' => 0,
        ];
        foreach ($userTypeRows as $row) {
            $key = (string) ($row['segmento'] ?? '');
            if (array_key_exists($key, $userTypeMap)) {
                $userTypeMap[$key] = (int) ($row['total'] ?? 0);
            }
        }

        $recent = $pdo->query(
            "SELECT c.id_contacto, c.nombre_completo, c.whatsapp, c.tipo_contacto, c.etapa_comercial,
                    c.ultima_interaccion_en, c.creado_en,
                    {$segmentExpression} segmento_principal,
                    CONCAT_WS(' ', u.nombre, u.apellidos) asesor
             FROM contactos c
             LEFT JOIN usuarios_sistema u ON u.id_usuario = c.id_asesor
             WHERE c.activo = 1
             ORDER BY COALESCE(c.ultima_interaccion_en, c.creado_en) DESC
             LIMIT 8"
        )->fetchAll(PDO::FETCH_ASSOC);

        $stageRows = $pdo->query(
            "SELECT etapa_comercial etapa, COUNT(*) total
             FROM contactos
             WHERE activo = 1
             GROUP BY etapa_comercial
             ORDER BY FIELD(
                etapa_comercial,
                'nuevo','informacion_solicitada','interesado','cotizacion_solicitada',
                'cotizacion_enviada','seguimiento','compra_confirmada','cerrado',
                'no_interesado','descartado'
             )"
        )->fetchAll(PDO::FETCH_ASSOC);

        $stageMap = [];
        foreach ($stageRows as $row) {
            $stageMap[(string) $row['etapa']] = (int) $row['total'];
        }
        $actionLabels = [
            'nuevo' => 'Nuevo contacto',
            'informacion_solicitada' => 'Solicitó información',
            'interesado' => 'Mostró interés',
            'cotizacion_solicitada' => 'Solicitó cotización',
            'cotizacion_enviada' => 'Cotización enviada',
            'seguimiento' => 'En seguimiento',
            'compra_confirmada' => 'Compra confirmada',
            'no_interesado' => 'No interesado',
            'cerrado' => 'Proceso cerrado',
            'descartado' => 'Descartado',
        ];

        $actionRows = $pdo->query(
            "SELECT segmento, etapa_comercial etapa, COUNT(*) total
             FROM (
                 SELECT c.id_contacto, c.etapa_comercial, {$segmentExpression} segmento
                 FROM contactos c
                 WHERE c.activo = 1
             ) acciones
             GROUP BY segmento, etapa_comercial
             ORDER BY FIELD(
                 etapa_comercial,
                 'nuevo','informacion_solicitada','interesado','cotizacion_solicitada',
                 'cotizacion_enviada','seguimiento','compra_confirmada','cerrado',
                 'no_interesado','descartado'
             )"
        )->fetchAll(PDO::FETCH_ASSOC);

        $actionsByType = [
            'lead' => [],
            'lead_recurrente' => [],
            'cliente' => [],
            'cliente_recurrente' => [],
        ];
        foreach ($actionRows as $row) {
            $type = (string) ($row['segmento'] ?? '');
            $stage = (string) ($row['etapa'] ?? '');
            if (!isset($actionsByType[$type]) || (int) ($row['total'] ?? 0) <= 0) {
                continue;
            }
            $actionsByType[$type][] = [
                'accion' => $stage,
                'etiqueta' => $actionLabels[$stage] ?? ucwords(str_replace('_', ' ', $stage)),
                'total' => (int) $row['total'],
            ];
        }

        $customerJourney = [
            [
                'tipo' => 'lead',
                'etiqueta' => 'Prospectos',
                'descripcion' => 'Prospecto en su primer contacto registrado por el bot o el equipo.',
                'total' => $userTypeMap['lead'],
                'acciones' => $actionsByType['lead'],
            ],
            [
                'tipo' => 'lead_recurrente',
                'etiqueta' => 'Prospectos recurrentes',
                'descripcion' => 'Prospecto que volvió a iniciar una nueva conversación.',
                'total' => $userTypeMap['lead_recurrente'],
                'acciones' => $actionsByType['lead_recurrente'],
            ],
            [
                'tipo' => 'cliente',
                'etiqueta' => 'Clientes',
                'descripcion' => 'Contacto reconocido como cliente, sin recompra posterior detectada.',
                'total' => $userTypeMap['cliente'],
                'acciones' => $actionsByType['cliente'],
            ],
            [
                'tipo' => 'cliente_recurrente',
                'etiqueta' => 'Clientes recurrentes',
                'descripcion' => 'Cliente que volvió después de convertirse o tiene compras repetidas.',
                'total' => $userTypeMap['cliente_recurrente'],
                'acciones' => $actionsByType['cliente_recurrente'],
            ],
        ];

        $start = (new DateTimeImmutable('first day of this month'))->sub(new DateInterval('P5M'));
        $stmt = $pdo->prepare(
            "SELECT DATE_FORMAT(creado_en, '%Y-%m') mes, COUNT(*) total
             FROM contactos WHERE creado_en >= :inicio GROUP BY DATE_FORMAT(creado_en, '%Y-%m') ORDER BY mes"
        );
        $stmt->execute(['inicio' => $start->format('Y-m-d 00:00:00')]);
        $found = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $found[$row['mes']] = (int) $row['total'];
        }

        $months = [];
        for ($i = 0; $i < 6; $i++) {
            $date = $start->add(new DateInterval('P' . $i . 'M'));
            $key = $date->format('Y-m');
            $months[] = [
                'mes' => $key,
                'etiqueta' => ucfirst($date->format('M')),
                'total' => $found[$key] ?? 0,
            ];
        }

        Response::success([
            'metricas' => [
                'total_contactos' => $total,
                'total_leads' => $leads,
                'total_pendientes' => (int) ($counts['total_pendientes'] ?? 0),
                'total_clientes' => $clients,
                'nuevos_hoy' => (int) ($counts['nuevos_hoy'] ?? 0),
                'cotizaciones_abiertas' => (int) ($quotes['cotizaciones_abiertas'] ?? 0),
                'monto_potencial' => (float) ($quotes['monto_potencial'] ?? 0),
                'interacciones_hoy' => $interactionsToday,
                'conversion' => $conversion,
            ],
            'conversion' => [
                'porcentaje' => $conversion,
                'base_leads_clientes' => $conversionBase,
                'convertidos' => $clients,
                'sin_convertir' => $leads,
            ],
            'atencion' => [
                'leads_atendidos' => (int) ($attention['leads_atendidos'] ?? 0),
                'leads_por_atender' => (int) ($attention['leads_por_atender'] ?? 0),
                'clientes_atendidos' => (int) ($attention['clientes_atendidos'] ?? 0),
                'clientes_por_atender' => (int) ($attention['clientes_por_atender'] ?? 0),
            ],
            'tipos_marketing' => [
                ['tipo' => 'lead', 'etiqueta' => 'Prospectos', 'total' => $userTypeMap['lead']],
                ['tipo' => 'lead_recurrente', 'etiqueta' => 'Prospectos recurrentes', 'total' => $userTypeMap['lead_recurrente']],
                ['tipo' => 'cliente', 'etiqueta' => 'Clientes', 'total' => $userTypeMap['cliente']],
                ['tipo' => 'cliente_recurrente', 'etiqueta' => 'Clientes recurrentes', 'total' => $userTypeMap['cliente_recurrente']],
            ],
            'contactos_recientes' => $recent,
            'customer_journey' => $customerJourney,
            'por_etapa' => array_map(static fn(array $r): array => [
                'etapa' => $r['etapa'],
                'total' => (int) $r['total'],
            ], $stageRows),
            'altas_mensuales' => $months,
        ]);
    }
}
