-- Barbora · Periodicidad de los servicios básicos: mensual o anual
-- (equivale a la migración 2026_11_07_000001_periodicidad_servicios_basicos).
--
--   expense_categories.frequency   'mensual' (luz, agua, internet…) o 'anual' (hosting…)
--
-- Se puede ejecutar varias veces.

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

SET @db = DATABASE();

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'expense_categories' AND COLUMN_NAME = 'frequency') = 0,
    'ALTER TABLE `expense_categories` ADD COLUMN `frequency` VARCHAR(10) NOT NULL DEFAULT ''mensual'' AFTER `recurring`',
    'SELECT ''expense_categories.frequency ya existe'''
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
