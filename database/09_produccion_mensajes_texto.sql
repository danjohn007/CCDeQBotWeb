-- =========================================================
-- CCdeQbot - Mensajes en producción: una sola plantilla de texto
-- Fecha: 2026-08-19
-- Ejecutar UNA sola vez después de 08_opcion_a_templates_y_estados.sql
-- No modifica la estructura de tablas; limpia configuración obsoleta de imagen.
-- =========================================================

DELETE FROM `mensajes_configuracion`
WHERE `clave` = 'marketing_template_image';

INSERT INTO `mensajes_configuracion` (`clave`,`valor`,`descripcion`) VALUES
('marketing_template_text','promocion_ccdeqbot_texto','Única plantilla MARKETING aprobada para mensajes fuera de la ventana de 24 h. BODY usa una variable posicional {{1}}.'),
('marketing_template_language','es_MX','Idioma exacto de la plantilla aprobada en Meta: Spanish (MEX).')
ON DUPLICATE KEY UPDATE
  `valor` = VALUES(`valor`),
  `descripcion` = VALUES(`descripcion`),
  `actualizado_en` = NOW();

-- Verificación:
-- SELECT clave, valor, descripcion FROM mensajes_configuracion ORDER BY clave;
