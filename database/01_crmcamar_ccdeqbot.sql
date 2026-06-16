-- =========================================================
-- CCdeQbot - Base de datos inicial para mini CRM
-- Compatible con MySQL 5.7+ y MariaDB 10.2+
-- Base de datos creada previamente desde cPanel:
-- crmcamar_ccdeqbot
-- =========================================================

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET time_zone = '-06:00';
SET FOREIGN_KEY_CHECKS = 0;

USE `crmcamar_ccdeqbot`;

-- =========================================================
-- 1. USUARIOS DEL DASHBOARD
-- =========================================================
CREATE TABLE IF NOT EXISTS `usuarios_sistema` (
    `id_usuario` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nombre` VARCHAR(100) NOT NULL,
    `apellidos` VARCHAR(120) DEFAULT NULL,
    `correo` VARCHAR(190) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `rol` ENUM('administrador', 'asesor', 'consulta') NOT NULL DEFAULT 'asesor',
    `activo` TINYINT(1) NOT NULL DEFAULT 1,
    `intentos_fallidos` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `bloqueado_hasta` DATETIME DEFAULT NULL,
    `ultimo_acceso` DATETIME DEFAULT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_usuario`),
    UNIQUE KEY `uq_usuarios_correo` (`correo`),
    KEY `idx_usuarios_rol_activo` (`rol`, `activo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- =========================================================
-- 2. CONTACTOS: LEADS, PENDIENTES Y CLIENTES
-- Un contacto inicia como lead y solo se convierte en cliente
-- cuando una compra sea confirmada desde el dashboard.
-- =========================================================
CREATE TABLE IF NOT EXISTS `contactos` (
    `id_contacto` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `whatsapp` VARCHAR(25) NOT NULL COMMENT 'Numero con codigo de pais, solo digitos',
    `nombre_completo` VARCHAR(180) DEFAULT NULL,
    `correo` VARCHAR(190) DEFAULT NULL,
    `empresa` VARCHAR(190) DEFAULT NULL,
    `tipo_contacto` ENUM('lead', 'cliente_pendiente', 'cliente') NOT NULL DEFAULT 'lead',
    `etapa_comercial` ENUM(
        'nuevo',
        'informacion_solicitada',
        'interesado',
        'cotizacion_solicitada',
        'cotizacion_enviada',
        'seguimiento',
        'compra_confirmada',
        'no_interesado',
        'cerrado',
        'descartado'
    ) NOT NULL DEFAULT 'nuevo',
    `origen` ENUM('whatsapp', 'dashboard', 'importacion') NOT NULL DEFAULT 'whatsapp',
    `cliente_declarado` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'El usuario indico que ya es cliente, pendiente de validar',
    `id_asesor` BIGINT UNSIGNED DEFAULT NULL,
    `proximo_seguimiento` DATETIME DEFAULT NULL,
    `primera_interaccion_en` DATETIME DEFAULT NULL,
    `ultima_interaccion_en` DATETIME DEFAULT NULL,
    `convertido_cliente_en` DATETIME DEFAULT NULL,
    `motivo_clasificacion` VARCHAR(255) DEFAULT NULL,
    `notas_generales` TEXT DEFAULT NULL,
    `activo` TINYINT(1) NOT NULL DEFAULT 1,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_contacto`),
    UNIQUE KEY `uq_contactos_whatsapp` (`whatsapp`),
    KEY `idx_contactos_tipo_etapa` (`tipo_contacto`, `etapa_comercial`),
    KEY `idx_contactos_ultima_interaccion` (`ultima_interaccion_en`),
    KEY `idx_contactos_seguimiento` (`proximo_seguimiento`),
    KEY `idx_contactos_asesor` (`id_asesor`),
    CONSTRAINT `fk_contactos_asesor`
        FOREIGN KEY (`id_asesor`) REFERENCES `usuarios_sistema` (`id_usuario`)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- =========================================================
-- 3. CATALOGO DE PRODUCTOS Y SERVICIOS
-- =========================================================
CREATE TABLE IF NOT EXISTS `productos_servicios` (
    `id_producto` SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `codigo_bot` VARCHAR(10) NOT NULL,
    `nombre` VARCHAR(180) NOT NULL,
    `descripcion` TEXT DEFAULT NULL,
    `precio` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `url_informacion` VARCHAR(500) DEFAULT NULL,
    `orden_visualizacion` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `activo` TINYINT(1) NOT NULL DEFAULT 1,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_producto`),
    UNIQUE KEY `uq_productos_codigo_bot` (`codigo_bot`),
    KEY `idx_productos_activo_orden` (`activo`, `orden_visualizacion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- =========================================================
-- 4. CONVERSACIONES DEL BOT
-- =========================================================
CREATE TABLE IF NOT EXISTS `conversaciones` (
    `id_conversacion` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_contacto` BIGINT UNSIGNED NOT NULL,
    `rama` ENUM('sin_definir', 'informacion', 'administracion', 'cotizacion') NOT NULL DEFAULT 'sin_definir',
    `estado` ENUM('activa', 'finalizada', 'abandonada', 'error') NOT NULL DEFAULT 'activa',
    `paso_inicial` VARCHAR(100) NOT NULL DEFAULT 'INICIO',
    `paso_final` VARCHAR(100) DEFAULT NULL,
    `resultado` VARCHAR(120) DEFAULT NULL,
    `datos_resumen` JSON DEFAULT NULL,
    `iniciada_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `ultima_actividad_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `finalizada_en` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id_conversacion`),
    KEY `idx_conversaciones_contacto_fecha` (`id_contacto`, `iniciada_en`),
    KEY `idx_conversaciones_estado_actividad` (`estado`, `ultima_actividad_en`),
    CONSTRAINT `fk_conversaciones_contacto`
        FOREIGN KEY (`id_contacto`) REFERENCES `contactos` (`id_contacto`)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- =========================================================
-- 5. SESION PERSISTENTE DEL BOT
-- Sustituye al objeto en memoria: const sessions = {};
-- =========================================================
CREATE TABLE IF NOT EXISTS `sesiones_bot` (
    `whatsapp` VARCHAR(25) NOT NULL,
    `id_contacto` BIGINT UNSIGNED DEFAULT NULL,
    `id_conversacion` BIGINT UNSIGNED DEFAULT NULL,
    `paso_actual` VARCHAR(100) NOT NULL DEFAULT 'INICIO',
    `datos` JSON DEFAULT NULL,
    `ultima_actividad_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `expira_en` DATETIME DEFAULT NULL,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`whatsapp`),
    KEY `idx_sesiones_contacto` (`id_contacto`),
    KEY `idx_sesiones_conversacion` (`id_conversacion`),
    KEY `idx_sesiones_expira` (`expira_en`),
    CONSTRAINT `fk_sesiones_contacto`
        FOREIGN KEY (`id_contacto`) REFERENCES `contactos` (`id_contacto`)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT `fk_sesiones_conversacion`
        FOREIGN KEY (`id_conversacion`) REFERENCES `conversaciones` (`id_conversacion`)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- =========================================================
-- 6. HISTORIAL DE MENSAJES E INTERACCIONES
-- El id de Meta evita procesar dos veces el mismo mensaje.
-- =========================================================
CREATE TABLE IF NOT EXISTS `interacciones` (
    `id_interaccion` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_contacto` BIGINT UNSIGNED NOT NULL,
    `id_conversacion` BIGINT UNSIGNED DEFAULT NULL,
    `direccion` ENUM('entrante', 'saliente', 'sistema') NOT NULL,
    `tipo_mensaje` ENUM('texto', 'boton', 'interactivo', 'plantilla', 'sistema', 'otro') NOT NULL DEFAULT 'texto',
    `meta_message_id` VARCHAR(191) DEFAULT NULL,
    `paso_flujo` VARCHAR(100) DEFAULT NULL,
    `contenido` TEXT DEFAULT NULL,
    `payload_json` JSON DEFAULT NULL,
    `estado_envio` ENUM('recibido', 'enviado', 'entregado', 'leido', 'error') NOT NULL,
    `detalle_error` TEXT DEFAULT NULL,
    `registrado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_interaccion`),
    UNIQUE KEY `uq_interacciones_meta_message` (`meta_message_id`),
    KEY `idx_interacciones_contacto_fecha` (`id_contacto`, `registrado_en`),
    KEY `idx_interacciones_conversacion_fecha` (`id_conversacion`, `registrado_en`),
    KEY `idx_interacciones_direccion_fecha` (`direccion`, `registrado_en`),
    CONSTRAINT `fk_interacciones_contacto`
        FOREIGN KEY (`id_contacto`) REFERENCES `contactos` (`id_contacto`)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT `fk_interacciones_conversacion`
        FOREIGN KEY (`id_conversacion`) REFERENCES `conversaciones` (`id_conversacion`)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- =========================================================
-- 7. SOLICITUDES GENERADAS EN LAS RAMAS A Y B
-- =========================================================
CREATE TABLE IF NOT EXISTS `solicitudes` (
    `id_solicitud` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `folio` VARCHAR(40) NOT NULL,
    `id_contacto` BIGINT UNSIGNED NOT NULL,
    `id_conversacion` BIGINT UNSIGNED DEFAULT NULL,
    `tipo_solicitud` ENUM(
        'informacion',
        'interes_compra',
        'factura',
        'contacto_administrativo',
        'contacto_asesor'
    ) NOT NULL,
    `id_producto` SMALLINT UNSIGNED DEFAULT NULL,
    `producto_nombre` VARCHAR(180) DEFAULT NULL COMMENT 'Copia historica del nombre mostrado por el bot',
    `cantidad` INT UNSIGNED DEFAULT NULL,
    `monto_estimado` DECIMAL(12,2) DEFAULT NULL,
    `prioridad` ENUM('normal', 'alta', 'urgente') NOT NULL DEFAULT 'normal',
    `estado` ENUM('nueva', 'asignada', 'en_seguimiento', 'atendida', 'cerrada', 'cancelada') NOT NULL DEFAULT 'nueva',
    `id_asesor` BIGINT UNSIGNED DEFAULT NULL,
    `detalle` TEXT DEFAULT NULL,
    `creada_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizada_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `cerrada_en` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id_solicitud`),
    UNIQUE KEY `uq_solicitudes_folio` (`folio`),
    KEY `idx_solicitudes_contacto_fecha` (`id_contacto`, `creada_en`),
    KEY `idx_solicitudes_tipo_estado` (`tipo_solicitud`, `estado`),
    KEY `idx_solicitudes_asesor_estado` (`id_asesor`, `estado`),
    CONSTRAINT `fk_solicitudes_contacto`
        FOREIGN KEY (`id_contacto`) REFERENCES `contactos` (`id_contacto`)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT `fk_solicitudes_conversacion`
        FOREIGN KEY (`id_conversacion`) REFERENCES `conversaciones` (`id_conversacion`)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT `fk_solicitudes_producto`
        FOREIGN KEY (`id_producto`) REFERENCES `productos_servicios` (`id_producto`)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT `fk_solicitudes_asesor`
        FOREIGN KEY (`id_asesor`) REFERENCES `usuarios_sistema` (`id_usuario`)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- =========================================================
-- 8. COTIZACIONES GENERADAS EN LA RAMA C
-- =========================================================
CREATE TABLE IF NOT EXISTS `cotizaciones` (
    `id_cotizacion` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `folio` VARCHAR(40) NOT NULL,
    `id_contacto` BIGINT UNSIGNED NOT NULL,
    `id_conversacion` BIGINT UNSIGNED DEFAULT NULL,
    `tipo_cliente_declarado` ENUM('primera_compra', 'cliente_existente', 'no_especificado') NOT NULL DEFAULT 'no_especificado',
    `correo_envio` VARCHAR(190) DEFAULT NULL,
    `tipo_entrega` ENUM('prioritaria', 'ordinaria', 'urgente') DEFAULT NULL,
    `subtotal` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `total` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `estado` ENUM('solicitada', 'en_revision', 'enviada', 'aceptada', 'rechazada', 'vencida', 'cancelada') NOT NULL DEFAULT 'solicitada',
    `id_asesor` BIGINT UNSIGNED DEFAULT NULL,
    `notas` TEXT DEFAULT NULL,
    `solicitada_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `enviada_en` DATETIME DEFAULT NULL,
    `valida_hasta` DATETIME DEFAULT NULL,
    `aceptada_en` DATETIME DEFAULT NULL,
    `actualizada_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_cotizacion`),
    UNIQUE KEY `uq_cotizaciones_folio` (`folio`),
    KEY `idx_cotizaciones_contacto_fecha` (`id_contacto`, `solicitada_en`),
    KEY `idx_cotizaciones_estado_fecha` (`estado`, `solicitada_en`),
    KEY `idx_cotizaciones_asesor_estado` (`id_asesor`, `estado`),
    CONSTRAINT `fk_cotizaciones_contacto`
        FOREIGN KEY (`id_contacto`) REFERENCES `contactos` (`id_contacto`)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT `fk_cotizaciones_conversacion`
        FOREIGN KEY (`id_conversacion`) REFERENCES `conversaciones` (`id_conversacion`)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT `fk_cotizaciones_asesor`
        FOREIGN KEY (`id_asesor`) REFERENCES `usuarios_sistema` (`id_usuario`)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `cotizacion_detalles` (
    `id_detalle` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_cotizacion` BIGINT UNSIGNED NOT NULL,
    `id_producto` SMALLINT UNSIGNED DEFAULT NULL,
    `producto_nombre` VARCHAR(180) NOT NULL,
    `producto_descripcion` TEXT DEFAULT NULL,
    `cantidad` INT UNSIGNED NOT NULL,
    `precio_unitario` DECIMAL(12,2) NOT NULL,
    `importe` DECIMAL(12,2) NOT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_detalle`),
    KEY `idx_cotizacion_detalles_cotizacion` (`id_cotizacion`),
    KEY `idx_cotizacion_detalles_producto` (`id_producto`),
    CONSTRAINT `fk_cotizacion_detalles_cotizacion`
        FOREIGN KEY (`id_cotizacion`) REFERENCES `cotizaciones` (`id_cotizacion`)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT `fk_cotizacion_detalles_producto`
        FOREIGN KEY (`id_producto`) REFERENCES `productos_servicios` (`id_producto`)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- =========================================================
-- 9. VENTAS / COMPRAS CONFIRMADAS
-- Una venta confirmada es la evidencia para convertir un lead
-- o cliente pendiente en cliente.
-- =========================================================
CREATE TABLE IF NOT EXISTS `ventas` (
    `id_venta` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `folio` VARCHAR(40) NOT NULL,
    `id_contacto` BIGINT UNSIGNED NOT NULL,
    `id_cotizacion` BIGINT UNSIGNED DEFAULT NULL,
    `monto_total` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `estado` ENUM('pendiente', 'confirmada', 'cancelada', 'reembolsada') NOT NULL DEFAULT 'pendiente',
    `metodo_pago` VARCHAR(80) DEFAULT NULL,
    `referencia_pago` VARCHAR(120) DEFAULT NULL,
    `fecha_compra` DATETIME DEFAULT NULL,
    `id_confirmado_por` BIGINT UNSIGNED DEFAULT NULL,
    `notas` TEXT DEFAULT NULL,
    `creada_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizada_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_venta`),
    UNIQUE KEY `uq_ventas_folio` (`folio`),
    KEY `idx_ventas_contacto_fecha` (`id_contacto`, `fecha_compra`),
    KEY `idx_ventas_estado_fecha` (`estado`, `fecha_compra`),
    CONSTRAINT `fk_ventas_contacto`
        FOREIGN KEY (`id_contacto`) REFERENCES `contactos` (`id_contacto`)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_ventas_cotizacion`
        FOREIGN KEY (`id_cotizacion`) REFERENCES `cotizaciones` (`id_cotizacion`)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT `fk_ventas_confirmado_por`
        FOREIGN KEY (`id_confirmado_por`) REFERENCES `usuarios_sistema` (`id_usuario`)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- =========================================================
-- 10. ETIQUETAS DEL CRM
-- =========================================================
CREATE TABLE IF NOT EXISTS `etiquetas` (
    `id_etiqueta` SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nombre` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(100) NOT NULL,
    `descripcion` VARCHAR(255) DEFAULT NULL,
    `color_fondo` CHAR(7) NOT NULL DEFAULT '#507B38',
    `color_texto` CHAR(7) NOT NULL DEFAULT '#FFFFFF',
    `activo` TINYINT(1) NOT NULL DEFAULT 1,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_etiqueta`),
    UNIQUE KEY `uq_etiquetas_nombre` (`nombre`),
    UNIQUE KEY `uq_etiquetas_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `contacto_etiquetas` (
    `id_contacto` BIGINT UNSIGNED NOT NULL,
    `id_etiqueta` SMALLINT UNSIGNED NOT NULL,
    `id_asignado_por` BIGINT UNSIGNED DEFAULT NULL,
    `asignada_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_contacto`, `id_etiqueta`),
    KEY `idx_contacto_etiquetas_etiqueta` (`id_etiqueta`),
    KEY `idx_contacto_etiquetas_usuario` (`id_asignado_por`),
    CONSTRAINT `fk_contacto_etiquetas_contacto`
        FOREIGN KEY (`id_contacto`) REFERENCES `contactos` (`id_contacto`)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT `fk_contacto_etiquetas_etiqueta`
        FOREIGN KEY (`id_etiqueta`) REFERENCES `etiquetas` (`id_etiqueta`)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT `fk_contacto_etiquetas_usuario`
        FOREIGN KEY (`id_asignado_por`) REFERENCES `usuarios_sistema` (`id_usuario`)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- =========================================================
-- 11. NOTAS INTERNAS DEL CONTACTO
-- =========================================================
CREATE TABLE IF NOT EXISTS `notas_contacto` (
    `id_nota` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_contacto` BIGINT UNSIGNED NOT NULL,
    `id_usuario` BIGINT UNSIGNED DEFAULT NULL,
    `nota` TEXT NOT NULL,
    `es_privada` TINYINT(1) NOT NULL DEFAULT 0,
    `creada_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizada_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_nota`),
    KEY `idx_notas_contacto_fecha` (`id_contacto`, `creada_en`),
    KEY `idx_notas_usuario` (`id_usuario`),
    CONSTRAINT `fk_notas_contacto`
        FOREIGN KEY (`id_contacto`) REFERENCES `contactos` (`id_contacto`)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT `fk_notas_usuario`
        FOREIGN KEY (`id_usuario`) REFERENCES `usuarios_sistema` (`id_usuario`)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- =========================================================
-- 12. HISTORIAL DE CAMBIOS DE CLASIFICACION Y ETAPA
-- =========================================================
CREATE TABLE IF NOT EXISTS `historial_estados` (
    `id_historial` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_contacto` BIGINT UNSIGNED NOT NULL,
    `id_usuario` BIGINT UNSIGNED DEFAULT NULL,
    `tipo_anterior` ENUM('lead', 'cliente_pendiente', 'cliente') DEFAULT NULL,
    `tipo_nuevo` ENUM('lead', 'cliente_pendiente', 'cliente') DEFAULT NULL,
    `etapa_anterior` VARCHAR(60) DEFAULT NULL,
    `etapa_nueva` VARCHAR(60) DEFAULT NULL,
    `motivo` VARCHAR(500) DEFAULT NULL,
    `origen_cambio` ENUM('bot', 'dashboard', 'sistema') NOT NULL DEFAULT 'dashboard',
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_historial`),
    KEY `idx_historial_contacto_fecha` (`id_contacto`, `creado_en`),
    KEY `idx_historial_usuario` (`id_usuario`),
    CONSTRAINT `fk_historial_contacto`
        FOREIGN KEY (`id_contacto`) REFERENCES `contactos` (`id_contacto`)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT `fk_historial_usuario`
        FOREIGN KEY (`id_usuario`) REFERENCES `usuarios_sistema` (`id_usuario`)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- =========================================================
-- 13. AUDITORIA DE ACCIONES DEL DASHBOARD Y LA API
-- =========================================================
CREATE TABLE IF NOT EXISTS `auditoria` (
    `id_auditoria` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_usuario` BIGINT UNSIGNED DEFAULT NULL,
    `accion` VARCHAR(100) NOT NULL,
    `entidad` VARCHAR(80) NOT NULL,
    `id_entidad` BIGINT UNSIGNED DEFAULT NULL,
    `descripcion` VARCHAR(500) DEFAULT NULL,
    `ip` VARCHAR(45) DEFAULT NULL,
    `user_agent` VARCHAR(255) DEFAULT NULL,
    `datos_anteriores` JSON DEFAULT NULL,
    `datos_nuevos` JSON DEFAULT NULL,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_auditoria`),
    KEY `idx_auditoria_usuario_fecha` (`id_usuario`, `creado_en`),
    KEY `idx_auditoria_entidad_fecha` (`entidad`, `id_entidad`, `creado_en`),
    CONSTRAINT `fk_auditoria_usuario`
        FOREIGN KEY (`id_usuario`) REFERENCES `usuarios_sistema` (`id_usuario`)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- =========================================================
-- DATOS INICIALES DEL CATALOGO ACTUAL DEL BOT
-- =========================================================
INSERT INTO `productos_servicios`
    (`codigo_bot`, `nombre`, `descripcion`, `precio`, `url_informacion`, `orden_visualizacion`, `activo`)
VALUES
    ('a', 'Producto/servicio M', 'Descripcion breve del producto M.', 100.00, 'https://tuempresa.com/producto-m', 1, 1),
    ('b', 'Producto/servicio N', 'Descripcion breve del producto N.', 200.00, 'https://tuempresa.com/producto-n', 2, 1),
    ('c', 'Producto/servicio O', 'Descripcion breve del producto O.', 300.00, 'https://tuempresa.com/producto-o', 3, 1),
    ('d', 'Producto/servicio P', 'Descripcion breve del producto P.', 400.00, 'https://tuempresa.com/producto-p', 4, 1)
ON DUPLICATE KEY UPDATE
    `nombre` = VALUES(`nombre`),
    `descripcion` = VALUES(`descripcion`),
    `precio` = VALUES(`precio`),
    `url_informacion` = VALUES(`url_informacion`),
    `orden_visualizacion` = VALUES(`orden_visualizacion`),
    `activo` = VALUES(`activo`);

-- =========================================================
-- ETIQUETAS INICIALES DEL MINI CRM
-- =========================================================
INSERT INTO `etiquetas`
    (`nombre`, `slug`, `descripcion`, `color_fondo`, `color_texto`, `activo`)
VALUES
    ('Nuevo lead', 'nuevo-lead', 'Contacto que inicio una interaccion recientemente.', '#A0BE38', '#FFFFFF', 1),
    ('Cliente pendiente', 'cliente-pendiente', 'Indico que ya es cliente, pero falta validar su compra.', '#D99B2B', '#FFFFFF', 1),
    ('Cliente', 'cliente', 'Contacto con compra confirmada.', '#507B38', '#FFFFFF', 1),
    ('Cotizacion urgente', 'cotizacion-urgente', 'Solicitud con entrega urgente.', '#C0392B', '#FFFFFF', 1),
    ('Facturacion', 'facturacion', 'Solicito apoyo para generar una factura.', '#2F6F9F', '#FFFFFF', 1),
    ('Requiere seguimiento', 'requiere-seguimiento', 'Contacto pendiente de atencion por un asesor.', '#6C757D', '#FFFFFF', 1),
    ('No respondio', 'no-respondio', 'Conversacion sin respuesta o abandonada.', '#8E6E53', '#FFFFFF', 1)
ON DUPLICATE KEY UPDATE
    `descripcion` = VALUES(`descripcion`),
    `color_fondo` = VALUES(`color_fondo`),
    `color_texto` = VALUES(`color_texto`),
    `activo` = VALUES(`activo`);

SET FOREIGN_KEY_CHECKS = 1;

-- =========================================================
-- FIN DEL SCRIPT
-- Se crean 13 modulos de datos y 15 tablas fisicas.
-- No se crea un usuario administrador en texto plano.
-- El administrador se insertara usando password_hash() desde PHP.
-- =========================================================
