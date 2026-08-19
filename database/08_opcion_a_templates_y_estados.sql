-- =========================================================
-- CCdeQbot - Opción A: promociones fuera de 24 h con templates
-- + corrección de estados Meta
-- Fecha: 2026-08-19
-- Ejecutar UNA sola vez DESPUÉS de 07_cola_mensajes_colonbot.sql
-- =========================================================

-- 1) Diferenciar "Meta aceptó el POST" del estado real enviado/entregado/leído.
ALTER TABLE `interacciones`
  MODIFY COLUMN `estado_envio`
    enum('recibido','aceptado_meta','enviado','entregado','leido','error')
    COLLATE utf8mb4_unicode_ci NOT NULL;

ALTER TABLE `mensajes_envios`
  MODIFY COLUMN `estado`
    enum('pendiente','procesando','aceptado_meta','enviado','entregado','leido','error')
    COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendiente',
  ADD COLUMN `tipo_envio` enum('libre','template') COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `segmento`,
  ADD COLUMN `ventana_24h_abierta` tinyint(1) DEFAULT NULL AFTER `tipo_envio`,
  ADD COLUMN `ultimo_mensaje_usuario_en` datetime DEFAULT NULL AFTER `ventana_24h_abierta`,
  ADD COLUMN `template_nombre` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `ultimo_mensaje_usuario_en`;

-- 2) Configuración no sensible de las plantillas aprobadas en Meta.
CREATE TABLE IF NOT EXISTS `mensajes_configuracion` (
  `clave` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `valor` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `actualizado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

INSERT INTO `mensajes_configuracion` (`clave`,`valor`,`descripcion`) VALUES
('marketing_template_text','promocion_ccdeqbot_texto','Template MARKETING aprobado sin imagen. BODY debe contener exactamente una variable posicional {{1}}.'),
('marketing_template_image','promocion_ccdeqbot_imagen','Template MARKETING aprobado con HEADER de imagen y BODY con una variable posicional {{1}}.'),
('marketing_template_language','es_MX','Código de idioma exacto usado al aprobar las plantillas en Meta.')
ON DUPLICATE KEY UPDATE
  `descripcion` = VALUES(`descripcion`),
  `actualizado_en` = NOW();

-- 3) Corrige filas históricas de la cola cuando el webhook ya registró
--    un estado más preciso en interacciones (incluye la prueba 131047).
UPDATE `mensajes_envios` me
JOIN `interacciones` i ON i.meta_message_id = me.meta_message_id
SET
  me.estado = CASE i.estado_envio
    WHEN 'aceptado_meta' THEN 'aceptado_meta'
    WHEN 'enviado' THEN 'enviado'
    WHEN 'entregado' THEN 'entregado'
    WHEN 'leido' THEN 'leido'
    WHEN 'error' THEN 'error'
    ELSE me.estado
  END,
  me.detalle_error = CASE
    WHEN i.estado_envio = 'error' THEN i.detalle_error
    ELSE me.detalle_error
  END,
  me.actualizado_en = NOW()
WHERE i.estado_envio IN ('aceptado_meta','enviado','entregado','leido','error');

-- Verificación rápida:
-- SELECT clave, valor FROM mensajes_configuracion ORDER BY clave;
-- SELECT id_envio, id_contacto, estado, tipo_envio, ventana_24h_abierta,
--        ultimo_mensaje_usuario_en, template_nombre, detalle_error
-- FROM mensajes_envios ORDER BY id_envio DESC LIMIT 20;
