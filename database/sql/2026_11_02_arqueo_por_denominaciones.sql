-- Barbora · Arqueo de caja por billetes y monedas
-- (equivale a la migración 2026_11_02_000002_arqueo_por_denominaciones).
--
--   companies.cash_count_mode            'total' (como hasta ahora) o 'denominations'
--   cash_sessions.closing_denominations  el conteo de billetes y monedas del cierre
--
-- Se puede ejecutar varias veces: solo añade lo que falta.

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

SET @db = DATABASE();

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'companies' AND COLUMN_NAME = 'cash_count_mode') = 0,
    'ALTER TABLE `companies` ADD COLUMN `cash_count_mode` VARCHAR(20) NOT NULL DEFAULT ''total'' AFTER `receipt_footer`',
    'SELECT ''companies.cash_count_mode ya existe'''
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'cash_sessions' AND COLUMN_NAME = 'closing_denominations') = 0,
    'ALTER TABLE `cash_sessions` ADD COLUMN `closing_denominations` JSON NULL AFTER `closing_amount`',
    'SELECT ''cash_sessions.closing_denominations ya existe'''
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

