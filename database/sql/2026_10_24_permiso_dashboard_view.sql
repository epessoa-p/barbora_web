-- Barbora · producción
-- Permiso nuevo «Ver Inicio» (dashboard.view): la app abre en el Inicio
-- (resumen del día) solo para quien lo tenga; los demás entran a la Agenda.
--
-- Solo datos, sin cambios de estructura. Seguro de reejecutar: no duplica nada.
--
-- Qué hace:
--   1. Crea el permiso si no existe.
--   2. Se lo da a los roles que ya pueden editar la configuración de la
--      barbería (settings.edit: el dueño / administrador) y al rol del
--      sistema «manager» (encargado).
-- A cualquier otro cargo se le puede dar después desde la web, en
-- Administración → Cargos → «Ver Inicio».

START TRANSACTION;

INSERT INTO `permissions` (`name`, `slug`, `module`, `description`, `created_at`, `updated_at`)
SELECT 'Ver Inicio', 'dashboard.view', 'dashboard',
       'Consultar el resumen del día (pantalla de inicio de la app)', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `slug` = 'dashboard.view');

INSERT INTO `role_permission` (`role_id`, `permission_id`)
SELECT DISTINCT r.`id`, p.`id`
FROM `roles` r
JOIN `permissions` p ON p.`slug` = 'dashboard.view'
WHERE r.`deleted_at` IS NULL
  AND (
        r.`id` IN (
            SELECT rp.`role_id`
            FROM `role_permission` rp
            JOIN `permissions` s ON s.`id` = rp.`permission_id`
            WHERE s.`slug` = 'settings.edit'
        )
     OR (r.`company_id` IS NULL AND r.`slug` = 'manager')
  )
  AND NOT EXISTS (
        SELECT 1 FROM `role_permission` x
        WHERE x.`role_id` = r.`id` AND x.`permission_id` = p.`id`
  );

COMMIT;
