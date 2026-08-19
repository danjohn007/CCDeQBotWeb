-- =============================================================
-- CCdeQbot CRM
-- Ajustes: productos/servicios y archivos adjuntos en cotizaciones
-- Ejecutar una sola vez en la base crmcamar_ccdeqbot.
-- =============================================================

USE `crmcamar_ccdeqbot`;

CREATE TABLE IF NOT EXISTS `productos_servicios` (
  `id_producto` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre` VARCHAR(180) NOT NULL,
  `tipo` ENUM('producto','servicio','membresia') NOT NULL DEFAULT 'servicio',
  `categoria` VARCHAR(120) NULL,
  `descripcion` TEXT NULL,
  `precio` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `unidad` VARCHAR(80) NULL,
  `requiere_afiliacion` TINYINT(1) NOT NULL DEFAULT 0,
  `pago_en_linea` TINYINT(1) NOT NULL DEFAULT 0,
  `link_pago` VARCHAR(500) NULL,
  `visible` TINYINT(1) NOT NULL DEFAULT 1,
  `archivado` TINYINT(1) NOT NULL DEFAULT 0,
  `notas_flujo` TEXT NULL,
  `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_producto`),
  KEY `idx_tipo` (`tipo`),
  KEY `idx_visible` (`visible`),
  KEY `idx_archivado` (`archivado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cotizacion_archivos` (
  `id_archivo` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_cotizacion` BIGINT UNSIGNED NOT NULL,
  `archivo_nombre_original` VARCHAR(255) NOT NULL,
  `archivo_nombre_guardado` VARCHAR(255) NOT NULL,
  `archivo_url` VARCHAR(500) NOT NULL,
  `mime_type` VARCHAR(120) NULL,
  `tamano_bytes` INT UNSIGNED NOT NULL DEFAULT 0,
  `subido_por` INT NULL,
  `subido_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `activo` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_archivo`),
  KEY `idx_cotizacion_activo` (`id_cotizacion`, `activo`),
  KEY `idx_subido_en` (`subido_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
