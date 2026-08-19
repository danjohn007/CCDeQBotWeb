-- Ajustes para productos/servicios: quitar membresía del catálogo y permitir desarchivar desde backend.
-- Ejecuta este archivo después de la reparación de productos, con la base crmcamar_ccdeqbot seleccionada.

UPDATE `productos_servicios`
SET `tipo` = 'servicio'
WHERE `tipo` = 'membresia';

ALTER TABLE `productos_servicios`
MODIFY `tipo` ENUM('producto','servicio') NOT NULL DEFAULT 'servicio';
