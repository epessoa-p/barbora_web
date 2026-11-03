-- Barbora · Galería de fotos por servicio (los cortes de los cuadros de la pared)
-- (equivale a la migración 2026_11_06_000001_galeria_de_servicios).
--
--   service_photos   varias fotos por servicio, cada una con su comentario.
--                    La primera (sort_order) es la portada y se copia a services.photo.
--
-- La foto que ya tenía cada servicio pasa a ser la primera de su galería.
-- Se puede ejecutar varias veces.

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `service_photos` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id` BIGINT UNSIGNED NOT NULL,
    `service_id` BIGINT UNSIGNED NOT NULL,
    `path` VARCHAR(255) NOT NULL,
    `caption` VARCHAR(120) NULL,
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    KEY `service_photos_service_id_sort_order_index` (`service_id`, `sort_order`),
    KEY `service_photos_company_id_foreign` (`company_id`),
    CONSTRAINT `service_photos_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
    CONSTRAINT `service_photos_service_id_foreign` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `service_photos` (`company_id`, `service_id`, `path`, `sort_order`, `created_at`, `updated_at`)
SELECT s.`company_id`, s.`id`, s.`photo`, 0, NOW(), NOW()
  FROM `services` s
 WHERE s.`photo` IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM `service_photos` p WHERE p.`service_id` = s.`id`);
