<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use PDO;

final class ProductController
{
    private const TYPES = ['producto', 'servicio'];

    public static function index(): never
    {
        Auth::requireAuth();
        $pdo = Database::connection();
        self::ensureTable($pdo);

        $where = ['1=1'];
        $params = [];

        if (empty($_GET['incluir_archivados'])) {
            $where[] = 'archivado = 0';
        }

        $q = trim((string) ($_GET['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(nombre LIKE :q_nombre OR categoria LIKE :q_categoria OR descripcion LIKE :q_descripcion)';
            $like = '%' . $q . '%';
            $params['q_nombre'] = $like;
            $params['q_categoria'] = $like;
            $params['q_descripcion'] = $like;
        }

        $tipo = (string) ($_GET['tipo'] ?? '');
        if (in_array($tipo, self::TYPES, true)) {
            $where[] = 'tipo = :tipo';
            $params['tipo'] = $tipo;
        }

        $whereSql = implode(' AND ', $where);
        $stmt = $pdo->prepare("SELECT * FROM productos_servicios WHERE {$whereSql} ORDER BY archivado ASC, visible DESC, actualizado_en DESC, id_producto DESC");
        $stmt->execute($params);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($items as &$item) {
            self::normalizeRow($item);
        }

        Response::success(['items' => $items]);
    }

    public static function show(int $id): never
    {
        Auth::requireAuth();
        $pdo = Database::connection();
        self::ensureTable($pdo);

        $stmt = $pdo->prepare('SELECT * FROM productos_servicios WHERE id_producto = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$item) {
            Response::error('El producto o servicio no existe.', 404);
        }
        self::normalizeRow($item);
        Response::success(['producto' => $item]);
    }

    public static function create(): never
    {
        $user = Auth::requireRoles(['superadministrador', 'administrador', 'asesor']);
        $pdo = Database::connection();
        self::ensureTable($pdo);
        $data = self::payload();

        $stmt = $pdo->prepare(
            'INSERT INTO productos_servicios
             (nombre, tipo, categoria, descripcion, precio, unidad, requiere_afiliacion, pago_en_linea, link_pago, visible, notas_flujo)
             VALUES (:nombre, :tipo, :categoria, :descripcion, :precio, :unidad, :requiere_afiliacion, :pago_en_linea, :link_pago, :visible, :notas_flujo)'
        );
        $stmt->execute($data);
        $id = (int) $pdo->lastInsertId();
        Audit::log('crear', 'producto_servicio', $id, 'Producto o servicio creado', null, $data, (int) $user['id_usuario']);
        Response::success(['id_producto' => $id], 201, 'Producto o servicio creado correctamente.');
    }

    public static function update(int $id): never
    {
        $user = Auth::requireRoles(['superadministrador', 'administrador', 'asesor']);
        $pdo = Database::connection();
        self::ensureTable($pdo);

        $current = $pdo->prepare('SELECT * FROM productos_servicios WHERE id_producto = :id LIMIT 1');
        $current->execute(['id' => $id]);
        $before = $current->fetch(PDO::FETCH_ASSOC);
        if (!$before) {
            Response::error('El producto o servicio no existe.', 404);
        }

        $data = self::payload();
        $data['id'] = $id;
        $stmt = $pdo->prepare(
            'UPDATE productos_servicios
             SET nombre = :nombre, tipo = :tipo, categoria = :categoria, descripcion = :descripcion,
                 precio = :precio, unidad = :unidad, requiere_afiliacion = :requiere_afiliacion,
                 pago_en_linea = :pago_en_linea, link_pago = :link_pago, visible = :visible,
                 notas_flujo = :notas_flujo, actualizado_en = NOW()
             WHERE id_producto = :id'
        );
        $stmt->execute($data);
        Audit::log('actualizar', 'producto_servicio', $id, 'Producto o servicio actualizado', $before, $data, (int) $user['id_usuario']);
        Response::success(null, 200, 'Producto o servicio actualizado correctamente.');
    }

    public static function archive(int $id): never
    {
        $user = Auth::requireRoles(['superadministrador', 'administrador', 'asesor']);
        $pdo = Database::connection();
        self::ensureTable($pdo);
        $stmt = $pdo->prepare('UPDATE productos_servicios SET archivado = 1, visible = 0, actualizado_en = NOW() WHERE id_producto = :id');
        $stmt->execute(['id' => $id]);
        Audit::log('archivar', 'producto_servicio', $id, 'Producto o servicio archivado', null, null, (int) $user['id_usuario']);
        Response::success(null, 200, 'Producto o servicio archivado.');
    }

    public static function restore(int $id): never
    {
        $user = Auth::requireRoles(['superadministrador', 'administrador', 'asesor']);
        $pdo = Database::connection();
        self::ensureTable($pdo);
        $stmt = $pdo->prepare('UPDATE productos_servicios SET archivado = 0, visible = 1, actualizado_en = NOW() WHERE id_producto = :id');
        $stmt->execute(['id' => $id]);
        Audit::log('desarchivar', 'producto_servicio', $id, 'Producto o servicio desarchivado', null, null, (int) $user['id_usuario']);
        Response::success(null, 200, 'Producto o servicio desarchivado.');
    }

    public static function delete(int $id): never
    {
        $user = Auth::requireRoles(['superadministrador', 'administrador']);
        $pdo = Database::connection();
        self::ensureTable($pdo);
        $stmt = $pdo->prepare('DELETE FROM productos_servicios WHERE id_producto = :id');
        $stmt->execute(['id' => $id]);
        Audit::log('eliminar', 'producto_servicio', $id, 'Producto o servicio eliminado', null, null, (int) $user['id_usuario']);
        Response::success(null, 200, 'Producto o servicio eliminado.');
    }

    public static function ensureTable(PDO $pdo): void
    {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS productos_servicios (
                id_producto INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                nombre VARCHAR(180) NOT NULL,
                tipo ENUM('producto','servicio') NOT NULL DEFAULT 'servicio',
                categoria VARCHAR(120) NULL,
                descripcion TEXT NULL,
                precio DECIMAL(12,2) NOT NULL DEFAULT 0,
                unidad VARCHAR(80) NULL,
                requiere_afiliacion TINYINT(1) NOT NULL DEFAULT 0,
                pago_en_linea TINYINT(1) NOT NULL DEFAULT 0,
                link_pago VARCHAR(500) NULL,
                visible TINYINT(1) NOT NULL DEFAULT 1,
                archivado TINYINT(1) NOT NULL DEFAULT 0,
                notas_flujo TEXT NULL,
                creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_tipo (tipo),
                INDEX idx_visible (visible),
                INDEX idx_archivado (archivado)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private static function payload(): array
    {
        $data = Request::json();
        $nombre = trim((string) ($data['nombre'] ?? ''));
        if ($nombre === '') {
            Response::error('Escribe el nombre del producto o servicio.', 422);
        }

        $tipo = (string) ($data['tipo'] ?? 'servicio');
        if (!in_array($tipo, self::TYPES, true)) {
            $tipo = 'servicio';
        }

        $precio = (float) ($data['precio'] ?? 0);
        if ($precio < 0) {
            Response::error('El precio no puede ser negativo.', 422);
        }

        $link = trim((string) ($data['link_pago'] ?? ''));
        if ($link !== '' && !filter_var($link, FILTER_VALIDATE_URL)) {
            Response::error('El link de pago no tiene un formato válido.', 422);
        }

        return [
            'nombre' => mb_substr($nombre, 0, 180),
            'tipo' => $tipo,
            'categoria' => self::nullableText((string) ($data['categoria'] ?? ''), 120),
            'descripcion' => self::nullableText((string) ($data['descripcion'] ?? ''), 2000),
            'precio' => $precio,
            'unidad' => self::nullableText((string) ($data['unidad'] ?? ''), 80),
            'requiere_afiliacion' => !empty($data['requiere_afiliacion']) ? 1 : 0,
            'pago_en_linea' => !empty($data['pago_en_linea']) ? 1 : 0,
            'link_pago' => $link !== '' ? $link : null,
            'visible' => isset($data['visible']) ? (!empty($data['visible']) ? 1 : 0) : 1,
            'notas_flujo' => self::nullableText((string) ($data['notas_flujo'] ?? ''), 2000),
        ];
    }

    private static function nullableText(string $value, int $length): ?string
    {
        $value = trim($value);
        return $value === '' ? null : mb_substr($value, 0, $length);
    }

    private static function normalizeRow(array &$item): void
    {
        $item['id_producto'] = (int) $item['id_producto'];
        $item['precio'] = (float) $item['precio'];
        $item['requiere_afiliacion'] = isset($item['requiere_afiliacion']) ? (int) $item['requiere_afiliacion'] : 0;
        $item['pago_en_linea'] = isset($item['pago_en_linea']) ? (int) $item['pago_en_linea'] : 0;
        $item['visible'] = isset($item['visible']) ? (int) $item['visible'] : 1;
        $item['archivado'] = isset($item['archivado']) ? (int) $item['archivado'] : 0;
    }
}
