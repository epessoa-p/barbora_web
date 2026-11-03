-- Barbora · Categorías de gasto «servicio básico / recurrente»
-- (equivale a la migración 2026_11_04_000002_categorias_de_gasto_recurrentes).
--
--   expense_categories.recurring   alquiler, luz, agua, internet… (lo mensual o anual)
--
-- Requiere antes 2026_11_03_finanzas_y_compras.sql. Se puede ejecutar varias veces.

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

SET @db = DATABASE();

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'expense_categories' AND COLUMN_NAME = 'recurring') = 0,
    'ALTER TABLE `expense_categories` ADD COLUMN `recurring` TINYINT(1) NOT NULL DEFAULT 0 AFTER `payee`',
    'SELECT ''expense_categories.recurring ya existe'''
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Las habituales que ya se crearon a cada barbería.
UPDATE `expense_categories`
SET `recurring` = 1
WHERE `name` IN ('ALQUILER', 'LUZ', 'AGUA', 'INTERNET Y TELÉFONO');
