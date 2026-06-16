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
        $clients = (int) ($counts['total_clientes'] ?? 0);
        $conversion = $total > 0 ? round(($clients / $total) * 100, 1) : 0.0;

        $recent = $pdo->query(
            "SELECT c.id_contacto, c.nombre_completo, c.whatsapp, c.tipo_contacto, c.etapa_comercial,
                    c.ultima_interaccion_en, c.creado_en,
                    CONCAT_WS(' ', u.nombre, u.apellidos) asesor
             FROM contactos c
             LEFT JOIN usuarios_sistema u ON u.id_usuario = c.id_asesor
             WHERE c.activo = 1
             ORDER BY COALESCE(c.ultima_interaccion_en, c.creado_en) DESC
             LIMIT 8"
        )->fetchAll(PDO::FETCH_ASSOC);

        $stageRows = $pdo->query(
            "SELECT etapa_comercial etapa, COUNT(*) total
             FROM contactos WHERE activo = 1 GROUP BY etapa_comercial ORDER BY total DESC"
        )->fetchAll(PDO::FETCH_ASSOC);

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
                'total_leads' => (int) ($counts['total_leads'] ?? 0),
                'total_pendientes' => (int) ($counts['total_pendientes'] ?? 0),
                'total_clientes' => $clients,
                'nuevos_hoy' => (int) ($counts['nuevos_hoy'] ?? 0),
                'cotizaciones_abiertas' => (int) ($quotes['cotizaciones_abiertas'] ?? 0),
                'monto_potencial' => (float) ($quotes['monto_potencial'] ?? 0),
                'interacciones_hoy' => $interactionsToday,
                'conversion' => $conversion,
            ],
            'contactos_recientes' => $recent,
            'por_etapa' => array_map(static fn(array $r): array => [
                'etapa' => $r['etapa'],
                'total' => (int) $r['total'],
            ], $stageRows),
            'altas_mensuales' => $months,
        ]);
    }
}
