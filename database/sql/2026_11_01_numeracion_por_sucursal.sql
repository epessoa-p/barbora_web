-- Barbora · producción
-- Cubre la migración: 2026_11_01_000001_numeracion_por_sucursal.php
--
-- Códigos correlativos POR SUCURSAL: cada sucursal tiene su V-000001 (venta),
-- C-000001 (cita), T-000001 (turno de caja) e I-000001 (movimiento de
-- inventario). Y todo queda colgado de una sucursal:
--   - el personal tiene sucursal base (la de sus horarios; si no, la primera),
--   - las cajas, la de su responsable (si no, la primera),
--   - las citas, la del barbero,
--   - los turnos, la de su caja; las ventas, la de su turno / cita / barbero,
--   - los movimientos de inventario, la de su almacén (NULL si es central).
-- Las ventas existentes se RENUMERAN por sucursal en orden de alta, y los
-- movimientos de caja e inventario que las citan («Venta V-000012»,
-- «Anulación V-000012») se actualizan al número nuevo.
--
-- Requiere MySQL 8 o MariaDB 10.2+ (usa ROW_NUMBER).
-- Seguro de reejecutar: cada ALTER comprueba antes si ya está hecho, y el
-- renumerado da siempre el mismo resultado (orden por id).
--
-- Antes de ejecutarlo: subir el código (GitHub). Recomendado: copia de la base.

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------------
-- 1. Estructura
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `document_sequences` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `branch_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `type` VARCHAR(5) NOT NULL,
  `last_number` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `document_sequences_company_id_branch_id_type_unique` (`company_id`, `branch_id`, `type`),
  CONSTRAINT `document_sequences_company_id_foreign`
    FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- personal.branch_id
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personal' AND COLUMN_NAME = 'branch_id');
SET @sql := IF(@c = 0, 'ALTER TABLE `personal` ADD COLUMN `branch_id` BIGINT UNSIGNED NULL AFTER `company_id`', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @c := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personal' AND CONSTRAINT_NAME = 'personal_branch_id_foreign');
SET @sql := IF(@c = 0, 'ALTER TABLE `personal` ADD CONSTRAINT `personal_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- appointments.number
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'appointments' AND COLUMN_NAME = 'number');
SET @sql := IF(@c = 0, 'ALTER TABLE `appointments` ADD COLUMN `number` VARCHAR(20) NULL AFTER `branch_id`', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @c := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'appointments' AND INDEX_NAME = 'appointments_company_id_number_index');
SET @sql := IF(@c = 0, 'ALTER TABLE `appointments` ADD INDEX `appointments_company_id_number_index` (`company_id`, `number`)', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- cash_sessions.branch_id + number
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cash_sessions' AND COLUMN_NAME = 'branch_id');
SET @sql := IF(@c = 0, 'ALTER TABLE `cash_sessions` ADD COLUMN `branch_id` BIGINT UNSIGNED NULL AFTER `caja_id`', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @c := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cash_sessions' AND CONSTRAINT_NAME = 'cash_sessions_branch_id_foreign');
SET @sql := IF(@c = 0, 'ALTER TABLE `cash_sessions` ADD CONSTRAINT `cash_sessions_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cash_sessions' AND COLUMN_NAME = 'number');
SET @sql := IF(@c = 0, 'ALTER TABLE `cash_sessions` ADD COLUMN `number` VARCHAR(20) NULL AFTER `branch_id`', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @c := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cash_sessions' AND INDEX_NAME = 'cash_sessions_company_id_number_index');
SET @sql := IF(@c = 0, 'ALTER TABLE `cash_sessions` ADD INDEX `cash_sessions_company_id_number_index` (`company_id`, `number`)', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- stock_movements.branch_id + number
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'stock_movements' AND COLUMN_NAME = 'branch_id');
SET @sql := IF(@c = 0, 'ALTER TABLE `stock_movements` ADD COLUMN `branch_id` BIGINT UNSIGNED NULL AFTER `warehouse_id`', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @c := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'stock_movements' AND CONSTRAINT_NAME = 'stock_movements_branch_id_foreign');
SET @sql := IF(@c = 0, 'ALTER TABLE `stock_movements` ADD CONSTRAINT `stock_movements_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'stock_movements' AND COLUMN_NAME = 'number');
SET @sql := IF(@c = 0, 'ALTER TABLE `stock_movements` ADD COLUMN `number` VARCHAR(20) NULL AFTER `branch_id`', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @c := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'stock_movements' AND INDEX_NAME = 'stock_movements_company_id_number_index');
SET @sql := IF(@c = 0, 'ALTER TABLE `stock_movements` ADD INDEX `stock_movements_company_id_number_index` (`company_id`, `number`)', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Ventas: el número deja de ser único por empresa (se crea el nuevo índice
-- por sucursal al final, ya renumeradas).
SET @c := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sales' AND INDEX_NAME = 'sales_company_id_number_unique');
SET @sql := IF(@c > 0, 'ALTER TABLE `sales` DROP INDEX `sales_company_id_number_unique`', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ---------------------------------------------------------------------------
-- 2. Datos: a cada cosa su sucursal
-- ---------------------------------------------------------------------------

START TRANSACTION;

-- Sucursales sin código: S01, S02… (a continuación de las que ya tienen).
UPDATE `branches` b
JOIN (
    SELECT x.`id`,
           ROW_NUMBER() OVER (PARTITION BY x.`company_id` ORDER BY x.`id`)
             + (SELECT COUNT(*) FROM `branches` y
                WHERE y.`company_id` = x.`company_id` AND y.`code` IS NOT NULL AND y.`code` <> '') AS seq
    FROM `branches` x
    WHERE x.`code` IS NULL OR x.`code` = ''
) n ON n.`id` = b.`id`
SET b.`code` = CONCAT('S', LPAD(n.seq, 2, '0'));

-- La sucursal por defecto de cada empresa: la primera activa.
DROP TEMPORARY TABLE IF EXISTS `tmp_default_branch`;
CREATE TEMPORARY TABLE `tmp_default_branch` AS
SELECT `company_id`, `id` AS `branch_id`
FROM (
    SELECT `company_id`, `id`,
           ROW_NUMBER() OVER (PARTITION BY `company_id` ORDER BY `active` DESC, `id`) AS rn
    FROM `branches`
    WHERE `deleted_at` IS NULL
) t
WHERE rn = 1;

-- Personal: la sucursal donde más horarios tiene.
DROP TEMPORARY TABLE IF EXISTS `tmp_schedule_branch`;
CREATE TEMPORARY TABLE `tmp_schedule_branch` AS
SELECT `personal_id`, `branch_id`
FROM (
    SELECT `personal_id`, `branch_id`,
           ROW_NUMBER() OVER (PARTITION BY `personal_id` ORDER BY COUNT(*) DESC, `branch_id`) AS rn
    FROM `work_schedules`
    WHERE `branch_id` IS NOT NULL
    GROUP BY `personal_id`, `branch_id`
) t
WHERE rn = 1;

UPDATE `personal` p
LEFT JOIN `tmp_schedule_branch` s ON s.`personal_id` = p.`id`
LEFT JOIN `tmp_default_branch` d ON d.`company_id` = p.`company_id`
SET p.`branch_id` = COALESCE(s.`branch_id`, d.`branch_id`)
WHERE p.`branch_id` IS NULL;

-- Cajas: la de su responsable; si no, la primera.
UPDATE `cajas` c
LEFT JOIN `personal` p ON p.`id` = c.`personal_id`
LEFT JOIN `tmp_default_branch` d ON d.`company_id` = c.`company_id`
SET c.`branch_id` = COALESCE(p.`branch_id`, d.`branch_id`)
WHERE c.`branch_id` IS NULL;

-- Citas: la del barbero.
UPDATE `appointments` a
LEFT JOIN `personal` p ON p.`id` = a.`personal_id`
LEFT JOIN `tmp_default_branch` d ON d.`company_id` = a.`company_id`
SET a.`branch_id` = COALESCE(p.`branch_id`, d.`branch_id`)
WHERE a.`branch_id` IS NULL;

-- Turnos: la de su caja.
UPDATE `cash_sessions` cs
JOIN `cajas` c ON c.`id` = cs.`caja_id`
SET cs.`branch_id` = c.`branch_id`
WHERE cs.`branch_id` IS NULL;

-- Ventas: la de su turno, su cita o su barbero; si no, la primera.
UPDATE `sales` v
LEFT JOIN `cash_sessions` cs ON cs.`id` = v.`cash_session_id`
LEFT JOIN `appointments` a ON a.`id` = v.`appointment_id`
LEFT JOIN `personal` p ON p.`id` = v.`personal_id`
LEFT JOIN `tmp_default_branch` d ON d.`company_id` = v.`company_id`
SET v.`branch_id` = COALESCE(cs.`branch_id`, a.`branch_id`, p.`branch_id`, d.`branch_id`)
WHERE v.`branch_id` IS NULL;

-- Movimientos de inventario: la de su almacén (NULL si es central).
UPDATE `stock_movements` m
JOIN `warehouses` w ON w.`id` = m.`warehouse_id`
SET m.`branch_id` = w.`branch_id`
WHERE m.`branch_id` IS NULL AND w.`branch_id` IS NOT NULL;

-- ---------------------------------------------------------------------------
-- 3. Renumerar por sucursal, en orden de alta
-- ---------------------------------------------------------------------------

-- Ventas, guardando número viejo → nuevo para corregir las referencias.
DROP TEMPORARY TABLE IF EXISTS `tmp_sale_number`;
CREATE TEMPORARY TABLE `tmp_sale_number` AS
SELECT `id`, `company_id`, `number` AS `old_number`,
       CONCAT('V-', LPAD(ROW_NUMBER() OVER (PARTITION BY `company_id`, `branch_id` ORDER BY `id`), 6, '0')) AS `new_number`
FROM `sales`;

DELETE FROM `tmp_sale_number` WHERE `old_number` = `new_number`;

UPDATE `sales` v
JOIN `tmp_sale_number` n ON n.`id` = v.`id`
SET v.`number` = n.`new_number`;

-- Cada movimiento se corrige a partir de su valor original (una sola pasada),
-- así un número nuevo que coincide con uno viejo no se renombra dos veces.
UPDATE `cash_movements` m
JOIN `tmp_sale_number` n ON n.`company_id` = m.`company_id` AND n.`old_number` = m.`reference`
SET m.`reference` = n.`new_number`,
    m.`concept` = REPLACE(m.`concept`, n.`old_number`, n.`new_number`);

DROP TEMPORARY TABLE IF EXISTS `tmp_sale_number_2`;
CREATE TEMPORARY TABLE `tmp_sale_number_2` AS SELECT * FROM `tmp_sale_number`;

UPDATE `stock_movements` m
JOIN `tmp_sale_number_2` n ON n.`company_id` = m.`company_id`
     AND (m.`reference` = n.`old_number` OR m.`reference` = CONCAT('Anulación ', n.`old_number`))
SET m.`reference` = REPLACE(m.`reference`, n.`old_number`, n.`new_number`);

-- Citas, turnos y movimientos de inventario.
UPDATE `appointments` t
JOIN (SELECT `id`, ROW_NUMBER() OVER (PARTITION BY `company_id`, `branch_id` ORDER BY `id`) AS seq FROM `appointments`) n ON n.`id` = t.`id`
SET t.`number` = CONCAT('C-', LPAD(n.seq, 6, '0'));

UPDATE `cash_sessions` t
JOIN (SELECT `id`, ROW_NUMBER() OVER (PARTITION BY `company_id`, `branch_id` ORDER BY `id`) AS seq FROM `cash_sessions`) n ON n.`id` = t.`id`
SET t.`number` = CONCAT('T-', LPAD(n.seq, 6, '0'));

UPDATE `stock_movements` t
JOIN (SELECT `id`, ROW_NUMBER() OVER (PARTITION BY `company_id`, `branch_id` ORDER BY `id`) AS seq FROM `stock_movements`) n ON n.`id` = t.`id`
SET t.`number` = CONCAT('I-', LPAD(n.seq, 6, '0'));

-- Contadores al día: el siguiente número sigue al último usado.
INSERT INTO `document_sequences` (`company_id`, `branch_id`, `type`, `last_number`, `created_at`, `updated_at`)
SELECT `company_id`, COALESCE(`branch_id`, 0), 'V', MAX(CAST(SUBSTRING(`number`, 3) AS UNSIGNED)), NOW(), NOW()
FROM `sales` GROUP BY `company_id`, COALESCE(`branch_id`, 0)
ON DUPLICATE KEY UPDATE `last_number` = GREATEST(`last_number`, VALUES(`last_number`)), `updated_at` = NOW();

INSERT INTO `document_sequences` (`company_id`, `branch_id`, `type`, `last_number`, `created_at`, `updated_at`)
SELECT `company_id`, COALESCE(`branch_id`, 0), 'C', MAX(CAST(SUBSTRING(`number`, 3) AS UNSIGNED)), NOW(), NOW()
FROM `appointments` GROUP BY `company_id`, COALESCE(`branch_id`, 0)
ON DUPLICATE KEY UPDATE `last_number` = GREATEST(`last_number`, VALUES(`last_number`)), `updated_at` = NOW();

INSERT INTO `document_sequences` (`company_id`, `branch_id`, `type`, `last_number`, `created_at`, `updated_at`)
SELECT `company_id`, COALESCE(`branch_id`, 0), 'T', MAX(CAST(SUBSTRING(`number`, 3) AS UNSIGNED)), NOW(), NOW()
FROM `cash_sessions` GROUP BY `company_id`, COALESCE(`branch_id`, 0)
ON DUPLICATE KEY UPDATE `last_number` = GREATEST(`last_number`, VALUES(`last_number`)), `updated_at` = NOW();

INSERT INTO `document_sequences` (`company_id`, `branch_id`, `type`, `last_number`, `created_at`, `updated_at`)
SELECT `company_id`, COALESCE(`branch_id`, 0), 'I', MAX(CAST(SUBSTRING(`number`, 3) AS UNSIGNED)), NOW(), NOW()
FROM `stock_movements` GROUP BY `company_id`, COALESCE(`branch_id`, 0)
ON DUPLICATE KEY UPDATE `last_number` = GREATEST(`last_number`, VALUES(`last_number`)), `updated_at` = NOW();

COMMIT;

DROP TEMPORARY TABLE IF EXISTS `tmp_default_branch`;
DROP TEMPORARY TABLE IF EXISTS `tmp_schedule_branch`;
DROP TEMPORARY TABLE IF EXISTS `tmp_sale_number`;
DROP TEMPORARY TABLE IF EXISTS `tmp_sale_number_2`;

-- ---------------------------------------------------------------------------
-- 4. Número de venta único por sucursal
-- ---------------------------------------------------------------------------

SET @c := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sales' AND INDEX_NAME = 'sales_company_id_branch_id_number_unique');
SET @sql := IF(@c = 0, 'ALTER TABLE `sales` ADD UNIQUE `sales_company_id_branch_id_number_unique` (`company_id`, `branch_id`, `number`)', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
