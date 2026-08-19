-- Ajuste para módulo "Mi perfil"
-- Ejecutar seleccionando primero la base de datos crmcamar_ccdeqbot en phpMyAdmin.

DELIMITER $$
DROP PROCEDURE IF EXISTS add_column_if_missing_ccdeqbot $$
CREATE PROCEDURE add_column_if_missing_ccdeqbot(
    IN p_table_name VARCHAR(64),
    IN p_column_name VARCHAR(64),
    IN p_column_definition TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE BINARY TABLE_SCHEMA = BINARY DATABASE()
          AND BINARY TABLE_NAME = BINARY p_table_name
          AND BINARY COLUMN_NAME = BINARY p_column_name
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', p_table_name, '` ADD COLUMN `', p_column_name, '` ', p_column_definition);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END $$
DELIMITER ;

CALL add_column_if_missing_ccdeqbot('usuarios_sistema', 'foto_perfil', 'VARCHAR(500) NULL AFTER `ultimo_acceso`');

DROP PROCEDURE IF EXISTS add_column_if_missing_ccdeqbot;
