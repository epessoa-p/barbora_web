-- Barbora · Foto de los productos (lista, ficha y catálogo público)
-- (equivale a la migración 2026_11_05_000002_foto_de_productos).
--
--   products.photo   ruta de la imagen en el disco público (products/{company_id}/…)
--
-- Se puede ejecutar varias veces.

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

SET @db = DATABASE();

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'products' AND COLUMN_NAME = 'photo') = 0,
    'ALTER TABLE `products` ADD COLUMN `photo` VARCHAR(255) NULL AFTER `description`',
    'SELECT ''products.photo ya existe'''
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
