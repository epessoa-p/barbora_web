-- Barbora · Foto de los servicios (se ve en la lista y en el catálogo público)
-- (equivale a la migración 2026_11_04_000001_foto_de_servicios).
--
--   services.photo   ruta de la imagen en el disco público (services/{company_id}/…)
--
-- Se puede ejecutar varias veces.

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

SET @db = DATABASE();

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'services' AND COLUMN_NAME = 'photo') = 0,
    'ALTER TABLE `services` ADD COLUMN `photo` VARCHAR(255) NULL AFTER `description`',
    'SELECT ''services.photo ya existe'''
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
