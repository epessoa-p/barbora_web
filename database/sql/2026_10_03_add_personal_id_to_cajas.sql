-- Barbora · producción
-- Cubre la migración: 2026_10_03_000001_add_personal_id_to_cajas_table.php
--
-- Agrega cajas.personal_id para poder asignar una caja a un personal (barbero).
-- NULL = caja general/principal (como hasta ahora).
--
-- Ejecutar una sola vez en la base de datos de producción.

ALTER TABLE `cajas`
  ADD COLUMN `personal_id` BIGINT UNSIGNED NULL AFTER `branch_id`,
  ADD CONSTRAINT `cajas_personal_id_foreign`
    FOREIGN KEY (`personal_id`) REFERENCES `personal` (`id`) ON DELETE SET NULL;
