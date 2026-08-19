-- =========================================================
-- CCdeQbot - Cola programada de mensajes tipo ColonBot
-- Fecha: 2026-08-18
-- Ejecutar UNA sola vez en la BD crmcamar_ccdeqbot.
-- No elimina ni modifica datos existentes.
-- =========================================================

CREATE TABLE IF NOT EXISTS `mensajes_campanas` (
  `id_campana` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `mensaje` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `imagen_nombre` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `imagen_mime` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `imagen_base64` longtext COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `segmentos_json` json DEFAULT NULL,
  `creado_por` bigint(20) UNSIGNED DEFAULT NULL,
  `estado` enum('pendiente','procesando','completada','parcial','error','cancelada') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendiente',
  `total_destinatarios` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `enviados` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `errores` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `creado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_campana`),
  KEY `idx_mensajes_campanas_estado` (`estado`),
  KEY `idx_mensajes_campanas_creado` (`creado_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `mensajes_envios` (
  `id_envio` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_campana` bigint(20) UNSIGNED NOT NULL,
  `id_contacto` bigint(20) UNSIGNED NOT NULL,
  `whatsapp` varchar(25) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(180) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `segmento` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tipo_envio` enum('libre','template') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ventana_24h_abierta` tinyint(1) DEFAULT NULL,
  `ultimo_mensaje_usuario_en` datetime DEFAULT NULL,
  `template_nombre` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado` enum('pendiente','procesando','aceptado_meta','enviado','entregado','leido','error') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendiente',
  `intentos` tinyint(3) UNSIGNED NOT NULL DEFAULT '0',
  `meta_message_id` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta_message_ids_json` json DEFAULT NULL,
  `detalle_error` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `programado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `procesado_en` datetime DEFAULT NULL,
  `actualizado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_envio`),
  UNIQUE KEY `uq_mensajes_envios_campana_contacto` (`id_campana`,`id_contacto`),
  KEY `idx_mensajes_envios_estado` (`estado`,`id_envio`),
  KEY `idx_mensajes_envios_meta` (`meta_message_id`),
  KEY `idx_mensajes_envios_contacto` (`id_contacto`),
  CONSTRAINT `fk_mensajes_envios_campana`
    FOREIGN KEY (`id_campana`) REFERENCES `mensajes_campanas` (`id_campana`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_mensajes_envios_contacto`
    FOREIGN KEY (`id_contacto`) REFERENCES `contactos` (`id_contacto`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;


-- Configuración por defecto para promociones fuera de 24 h.
CREATE TABLE IF NOT EXISTS `mensajes_configuracion` (
  `clave` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `valor` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `actualizado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

INSERT INTO `mensajes_configuracion` (`clave`,`valor`,`descripcion`) VALUES
('marketing_template_text','promocion_ccdeqbot_texto','Template MARKETING aprobado sin imagen. BODY usa {{1}}.'),
('marketing_template_image','promocion_ccdeqbot_imagen','Template MARKETING aprobado con HEADER imagen y BODY usa {{1}}.'),
('marketing_template_language','es_MX','Idioma exacto aprobado en Meta.')
ON DUPLICATE KEY UPDATE `descripcion` = VALUES(`descripcion`), `actualizado_en` = NOW();
