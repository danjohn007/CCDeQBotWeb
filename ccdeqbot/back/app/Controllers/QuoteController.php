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

    public static function index(): never
    {
        Auth::requireAuth();
        $pdo = Database::connection();
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
            $where[] = '(co.folio LIKE :q OR c.nombre_completo LIKE :q OR c.whatsapp LIKE :q)';
            $params['q'] = '%' . $q . '%';
        }
        $whereSql = implode(' AND ', $where);

        $count = $pdo->prepare("SELECT COUNT(*) FROM cotizaciones co JOIN contactos c ON c.id_contacto = co.id_contacto WHERE {$whereSql}");
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $stmt = $pdo->prepare(
            "SELECT co.*, c.nombre_completo, c.whatsapp, c.tipo_contacto,
                    CONCAT_WS(' ', u.nombre, u.apellidos) asesor,
                    (SELECT GROUP_CONCAT(CONCAT(cd.producto_nombre, ' × ', cd.cantidad) SEPARATOR ', ')
                     FROM cotizacion_detalles cd WHERE cd.id_cotizacion = co.id_cotizacion) productos
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
}
