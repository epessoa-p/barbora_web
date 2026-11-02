-- Barbora · Catálogo público de servicios y productos
-- (equivale a la migración 2026_11_02_000003_catalogo_publico).
--
--   companies.catalog_enabled        si el catálogo público está publicado (nace apagado)
--   companies.catalog_token          el código del enlace /catalogo/{token}
--   companies.catalog_show_products  si el catálogo muestra también productos
--
-- Requiere antes 2026_11_02_arqueo_por_denominaciones.sql (va después de
-- companies.cash_count_mode). Se puede ejecutar varias veces.

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

SET @db = DATABASE();

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'companies' AND COLUMN_NAME = 'catalog_enabled') = 0,
    'ALTER TABLE `companies` ADD COLUMN `catalog_enabled` TINYINT(1) NOT NULL DEFAULT 0 AFTER `cash_count_mode`',
    'SELECT ''companies.catalog_enabled ya existe'''
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'companies' AND COLUMN_NAME = 'catalog_token') = 0,
    'ALTER TABLE `companies` ADD COLUMN `catalog_token` VARCHAR(32) NULL AFTER `catalog_enabled`',
    'SELECT ''companies.catalog_token ya existe'''
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'companies' AND INDEX_NAME = 'companies_catalog_token_unique') = 0,
    'ALTER TABLE `companies` ADD UNIQUE INDEX `companies_catalog_token_unique` (`catalog_token`)',
    'SELECT ''companies_catalog_token_unique ya existe'''
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'companies' AND COLUMN_NAME = 'catalog_show_products') = 0,
    'ALTER TABLE `companies` ADD COLUMN `catalog_show_products` TINYINT(1) NOT NULL DEFAULT 0 AFTER `catalog_token`',
    'SELECT ''companies.catalog_show_products ya existe'''
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
