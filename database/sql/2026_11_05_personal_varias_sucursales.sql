-- Barbora · Personal con varias sucursales
-- (equivale a la migración 2026_11_05_000001_personal_varias_sucursales).
--
--   branch_personal   sucursales ADICIONALES de una ficha de personal.
--                     La sucursal base sigue en personal.branch_id.
--
-- Se puede ejecutar varias veces.

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `branch_personal` (
    `branch_id` BIGINT UNSIGNED NOT NULL,
    `personal_id` BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (`branch_id`, `personal_id`),
    CONSTRAINT `branch_personal_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE,
    CONSTRAINT `branch_personal_personal_id_foreign` FOREIGN KEY (`personal_id`) REFERENCES `personal` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
