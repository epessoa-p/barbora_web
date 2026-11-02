-- Barbora · Las liquidaciones de comisiones se registran como gasto
-- (equivale a la migración 2026_11_03_000002_liquidaciones_como_gasto).
--
--   expenses.commission_settlement_id   la liquidación que originó el gasto
--
-- Requiere antes 2026_11_03_finanzas_y_compras.sql. Se puede ejecutar varias veces.

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

SET @db = DATABASE();

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'expenses' AND COLUMN_NAME = 'commission_settlement_id') = 0,
    'ALTER TABLE `expenses` ADD COLUMN `commission_settlement_id` BIGINT UNSIGNED NULL AFTER `personal_id`, ADD CONSTRAINT `expenses_commission_settlement_id_foreign` FOREIGN KEY (`commission_settlement_id`) REFERENCES `commission_settlements` (`id`) ON DELETE SET NULL',
    'SELECT ''expenses.commission_settlement_id ya existe'''
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
