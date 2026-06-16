-- =============================================================
-- CCdeQbot CRM
-- Migración para agregar el nivel SUPERADMINISTRADOR
-- Ejecutar una sola vez en la base crmcamar_ccdeqbot.
-- =============================================================

USE `crmcamar_ccdeqbot`;

ALTER TABLE `usuarios_sistema`
    MODIFY COLUMN `rol`
    ENUM('superadministrador', 'administrador', 'asesor', 'consulta')
    NOT NULL DEFAULT 'asesor';

-- Si ya existe algún usuario pero todavía no hay superadministrador,
-- se promueve primero al administrador más antiguo; si no existe uno,
-- se utiliza el usuario más antiguo. Si aún no hay usuarios, el
-- instalador crear-admin.php creará el primer superadministrador.
SET @cantidad_superadmins := (
    SELECT COUNT(*)
    FROM `usuarios_sistema`
    WHERE `rol` = 'superadministrador'
);

SET @usuario_candidato := COALESCE(
    (
        SELECT `id_usuario`
        FROM `usuarios_sistema`
        WHERE `rol` = 'administrador'
        ORDER BY `id_usuario` ASC
        LIMIT 1
    ),
    (
        SELECT `id_usuario`
        FROM `usuarios_sistema`
        ORDER BY `id_usuario` ASC
        LIMIT 1
    )
);

UPDATE `usuarios_sistema`
SET `rol` = 'superadministrador'
WHERE `id_usuario` = @usuario_candidato
  AND @cantidad_superadmins = 0
  AND @usuario_candidato IS NOT NULL;

SELECT `id_usuario`, `nombre`, `apellidos`, `correo`, `rol`, `activo`
FROM `usuarios_sistema`
ORDER BY `id_usuario`;
