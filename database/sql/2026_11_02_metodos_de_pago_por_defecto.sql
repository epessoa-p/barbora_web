-- Barbora · Métodos de pago por defecto para las barberías que no tienen ninguno
-- (equivale a la migración 2026_11_02_000001_metodos_de_pago_por_defecto).
--
-- Síntoma: al cobrar, en la web el desplegable de método de pago sale vacío y
-- en la app no se puede cobrar. Pasa con las barberías que ya existían cuando
-- se creó la tabla payment_methods (o que se cargaron por SQL).
--
-- Se puede ejecutar varias veces: solo toca barberías SIN ningún método.
-- Una barbería que ya tiene alguno (aunque esté de baja) no se modifica.

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

START TRANSACTION;

INSERT INTO `payment_methods`
    (`company_id`, `name`, `slug`, `counts_as_cash`, `requires_reference`, `active`, `sort_order`, `created_at`, `updated_at`)
SELECT c.`id`, d.`name`, d.`slug`, d.`counts_as_cash`, d.`requires_reference`, 1, d.`sort_order`, NOW(), NOW()
FROM `companies` c
CROSS JOIN (
    SELECT 'EFECTIVO' AS `name`, 'efectivo' AS `slug`, 1 AS `counts_as_cash`, 0 AS `requires_reference`, 0 AS `sort_order`
    UNION ALL SELECT 'TARJETA', 'tarjeta', 0, 0, 1
    UNION ALL SELECT 'TRANSFERENCIA', 'transferencia', 0, 1, 2
    UNION ALL SELECT 'QR', 'qr', 0, 1, 3
) d
WHERE NOT EXISTS (
    SELECT 1 FROM `payment_methods` pm WHERE pm.`company_id` = c.`id`
);

COMMIT;

-- Comprobación: cuántos métodos activos tiene cada barbería (ninguna en 0).
SELECT c.`id`, c.`name`, COUNT(pm.`id`) AS `metodos_activos`
FROM `companies` c
LEFT JOIN `payment_methods` pm
       ON pm.`company_id` = c.`id` AND pm.`active` = 1 AND pm.`deleted_at` IS NULL
GROUP BY c.`id`, c.`name`
ORDER BY `metodos_activos`, c.`id`;
