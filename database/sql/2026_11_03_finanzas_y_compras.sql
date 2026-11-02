-- Barbora · Finanzas (cuentas y gastos) y Compras a proveedores
-- (equivale a la migración 2026_11_03_000001_finanzas_y_compras).
--
-- Crea:
--   finance_accounts, finance_movements      cuentas (capital, caja chica, banco…) y sus movimientos
--   expense_categories, expenses             gastos (salen de una cuenta o de la caja abierta)
--   suppliers                                proveedores
--   purchases, purchase_items, purchase_payments   compras, lo comprado y sus pagos
-- y los permisos nuevos, que reciben:
--   · todos: los roles que ya editan la configuración (settings.edit: dueño / administrador)
--   · consultar y registrar: el rol del sistema «manager» (encargado)
--
-- Se puede ejecutar varias veces: las tablas se crean solo si no existen y
-- los permisos no se duplican.
--
-- IMPORTANTE: el módulo de plan nuevo es «finanzas». Para que una barbería
-- vea Cuentas y Gastos, su plan tiene que incluirlo (Plataforma → Planes →
-- marcar «Finanzas»). Compras va con «Inventario», que ya existe. Al final
-- hay una línea comentada para añadir «finanzas» a todos los planes a la vez.

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `finance_accounts` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id` BIGINT UNSIGNED NOT NULL,
    `branch_id` BIGINT UNSIGNED NULL,
    `name` VARCHAR(255) NOT NULL,
    `type` VARCHAR(20) NOT NULL DEFAULT 'otro',
    `bank_name` VARCHAR(255) NULL,
    `account_number` VARCHAR(60) NULL,
    `opening_balance` DECIMAL(15,2) NOT NULL DEFAULT 0,
    `balance` DECIMAL(15,2) NOT NULL DEFAULT 0,
    `notes` TEXT NULL,
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    `deleted_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    KEY `finance_accounts_company_id_active_index` (`company_id`, `active`),
    CONSTRAINT `finance_accounts_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
    CONSTRAINT `finance_accounts_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `finance_movements` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id` BIGINT UNSIGNED NOT NULL,
    `finance_account_id` BIGINT UNSIGNED NOT NULL,
    `type` VARCHAR(10) NOT NULL,
    `origin` VARCHAR(20) NOT NULL DEFAULT 'manual',
    `concept` VARCHAR(255) NOT NULL,
    `amount` DECIMAL(15,2) NOT NULL,
    `balance_after` DECIMAL(15,2) NOT NULL,
    `occurred_on` DATE NOT NULL,
    `reference` VARCHAR(255) NULL,
    `transfer_movement_id` BIGINT UNSIGNED NULL,
    `created_by` BIGINT UNSIGNED NULL,
    `cancelled_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    KEY `finance_movements_finance_account_id_occurred_on_index` (`finance_account_id`, `occurred_on`),
    CONSTRAINT `finance_movements_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
    CONSTRAINT `finance_movements_finance_account_id_foreign` FOREIGN KEY (`finance_account_id`) REFERENCES `finance_accounts` (`id`) ON DELETE CASCADE,
    CONSTRAINT `finance_movements_transfer_movement_id_foreign` FOREIGN KEY (`transfer_movement_id`) REFERENCES `finance_movements` (`id`) ON DELETE SET NULL,
    CONSTRAINT `finance_movements_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `expense_categories` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id` BIGINT UNSIGNED NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `payee` VARCHAR(20) NOT NULL DEFAULT 'ninguno',
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    `sort_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `deleted_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `expense_categories_company_id_name_unique` (`company_id`, `name`),
    CONSTRAINT `expense_categories_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `suppliers` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id` BIGINT UNSIGNED NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `tax_id` VARCHAR(30) NULL,
    `contact_name` VARCHAR(255) NULL,
    `phone` VARCHAR(30) NULL,
    `email` VARCHAR(255) NULL,
    `address` VARCHAR(255) NULL,
    `notes` TEXT NULL,
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    `deleted_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    KEY `suppliers_company_id_active_index` (`company_id`, `active`),
    CONSTRAINT `suppliers_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `expenses` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id` BIGINT UNSIGNED NOT NULL,
    `branch_id` BIGINT UNSIGNED NULL,
    `number` VARCHAR(20) NULL,
    `expense_category_id` BIGINT UNSIGNED NOT NULL,
    `supplier_id` BIGINT UNSIGNED NULL,
    `personal_id` BIGINT UNSIGNED NULL,
    `description` VARCHAR(255) NOT NULL,
    `amount` DECIMAL(15,2) NOT NULL,
    `spent_on` DATE NOT NULL,
    `source` VARCHAR(10) NOT NULL,
    `finance_account_id` BIGINT UNSIGNED NULL,
    `finance_movement_id` BIGINT UNSIGNED NULL,
    `cash_session_id` BIGINT UNSIGNED NULL,
    `cash_movement_id` BIGINT UNSIGNED NULL,
    `reference` VARCHAR(255) NULL,
    `notes` TEXT NULL,
    `status` VARCHAR(15) NOT NULL DEFAULT 'registrado',
    `cancelled_at` TIMESTAMP NULL,
    `cancelled_by` BIGINT UNSIGNED NULL,
    `cancel_reason` VARCHAR(255) NULL,
    `created_by` BIGINT UNSIGNED NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    KEY `expenses_company_id_spent_on_index` (`company_id`, `spent_on`),
    KEY `expenses_company_id_number_index` (`company_id`, `number`),
    CONSTRAINT `expenses_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
    CONSTRAINT `expenses_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
    CONSTRAINT `expenses_expense_category_id_foreign` FOREIGN KEY (`expense_category_id`) REFERENCES `expense_categories` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `expenses_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL,
    CONSTRAINT `expenses_personal_id_foreign` FOREIGN KEY (`personal_id`) REFERENCES `personal` (`id`) ON DELETE SET NULL,
    CONSTRAINT `expenses_finance_account_id_foreign` FOREIGN KEY (`finance_account_id`) REFERENCES `finance_accounts` (`id`) ON DELETE SET NULL,
    CONSTRAINT `expenses_finance_movement_id_foreign` FOREIGN KEY (`finance_movement_id`) REFERENCES `finance_movements` (`id`) ON DELETE SET NULL,
    CONSTRAINT `expenses_cash_session_id_foreign` FOREIGN KEY (`cash_session_id`) REFERENCES `cash_sessions` (`id`) ON DELETE SET NULL,
    CONSTRAINT `expenses_cash_movement_id_foreign` FOREIGN KEY (`cash_movement_id`) REFERENCES `cash_movements` (`id`) ON DELETE SET NULL,
    CONSTRAINT `expenses_cancelled_by_foreign` FOREIGN KEY (`cancelled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `expenses_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `purchases` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id` BIGINT UNSIGNED NOT NULL,
    `branch_id` BIGINT UNSIGNED NULL,
    `number` VARCHAR(20) NULL,
    `supplier_id` BIGINT UNSIGNED NULL,
    `warehouse_id` BIGINT UNSIGNED NOT NULL,
    `invoice_number` VARCHAR(60) NULL,
    `purchased_on` DATE NOT NULL,
    `subtotal` DECIMAL(15,2) NOT NULL DEFAULT 0,
    `discount` DECIMAL(15,2) NOT NULL DEFAULT 0,
    `total` DECIMAL(15,2) NOT NULL DEFAULT 0,
    `paid` DECIMAL(15,2) NOT NULL DEFAULT 0,
    `status` VARCHAR(15) NOT NULL DEFAULT 'pendiente',
    `due_on` DATE NULL,
    `notes` TEXT NULL,
    `cancelled_at` TIMESTAMP NULL,
    `cancelled_by` BIGINT UNSIGNED NULL,
    `cancel_reason` VARCHAR(255) NULL,
    `created_by` BIGINT UNSIGNED NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    KEY `purchases_company_id_purchased_on_index` (`company_id`, `purchased_on`),
    KEY `purchases_company_id_number_index` (`company_id`, `number`),
    KEY `purchases_company_id_status_index` (`company_id`, `status`),
    CONSTRAINT `purchases_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
    CONSTRAINT `purchases_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
    CONSTRAINT `purchases_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL,
    CONSTRAINT `purchases_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `purchases_cancelled_by_foreign` FOREIGN KEY (`cancelled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `purchases_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `purchase_items` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `purchase_id` BIGINT UNSIGNED NOT NULL,
    `product_id` BIGINT UNSIGNED NOT NULL,
    `product_name` VARCHAR(255) NOT NULL,
    `quantity` DECIMAL(15,2) NOT NULL,
    `unit_cost` DECIMAL(15,2) NOT NULL,
    `total` DECIMAL(15,2) NOT NULL,
    `stock_movement_id` BIGINT UNSIGNED NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    CONSTRAINT `purchase_items_purchase_id_foreign` FOREIGN KEY (`purchase_id`) REFERENCES `purchases` (`id`) ON DELETE CASCADE,
    CONSTRAINT `purchase_items_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `purchase_items_stock_movement_id_foreign` FOREIGN KEY (`stock_movement_id`) REFERENCES `stock_movements` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `purchase_payments` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id` BIGINT UNSIGNED NOT NULL,
    `purchase_id` BIGINT UNSIGNED NOT NULL,
    `amount` DECIMAL(15,2) NOT NULL,
    `paid_on` DATE NOT NULL,
    `source` VARCHAR(10) NOT NULL,
    `finance_account_id` BIGINT UNSIGNED NULL,
    `finance_movement_id` BIGINT UNSIGNED NULL,
    `cash_session_id` BIGINT UNSIGNED NULL,
    `cash_movement_id` BIGINT UNSIGNED NULL,
    `reference` VARCHAR(255) NULL,
    `created_by` BIGINT UNSIGNED NULL,
    `cancelled_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    CONSTRAINT `purchase_payments_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
    CONSTRAINT `purchase_payments_purchase_id_foreign` FOREIGN KEY (`purchase_id`) REFERENCES `purchases` (`id`) ON DELETE CASCADE,
    CONSTRAINT `purchase_payments_finance_account_id_foreign` FOREIGN KEY (`finance_account_id`) REFERENCES `finance_accounts` (`id`) ON DELETE SET NULL,
    CONSTRAINT `purchase_payments_finance_movement_id_foreign` FOREIGN KEY (`finance_movement_id`) REFERENCES `finance_movements` (`id`) ON DELETE SET NULL,
    CONSTRAINT `purchase_payments_cash_session_id_foreign` FOREIGN KEY (`cash_session_id`) REFERENCES `cash_sessions` (`id`) ON DELETE SET NULL,
    CONSTRAINT `purchase_payments_cash_movement_id_foreign` FOREIGN KEY (`cash_movement_id`) REFERENCES `cash_movements` (`id`) ON DELETE SET NULL,
    CONSTRAINT `purchase_payments_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Permisos ────────────────────────────────────────────────────────────────

START TRANSACTION;

INSERT INTO `permissions` (`name`, `slug`, `module`, `description`, `created_at`, `updated_at`)
SELECT p.`name`, p.`slug`, p.`module`, p.`description`, NOW(), NOW()
FROM (
              SELECT 'Ver Cuentas' AS `name`, 'accounts.view' AS `slug`, 'accounts' AS `module`, 'Consultar las cuentas (capital, caja chica, banco) y sus movimientos' AS `description`
    UNION ALL SELECT 'Crear Cuentas', 'accounts.create', 'accounts', 'Dar de alta cuentas'
    UNION ALL SELECT 'Editar Cuentas', 'accounts.edit', 'accounts', 'Modificar las cuentas'
    UNION ALL SELECT 'Eliminar Cuentas', 'accounts.delete', 'accounts', 'Dar de baja cuentas'
    UNION ALL SELECT 'Crear Movimientos de cuenta', 'account_movements.create', 'account_movements', 'Registrar ingresos, egresos y transferencias entre cuentas'
    UNION ALL SELECT 'Eliminar Movimientos de cuenta', 'account_movements.delete', 'account_movements', 'Anular movimientos de cuenta'
    UNION ALL SELECT 'Ver Gastos', 'expenses.view', 'expenses', 'Consultar los gastos'
    UNION ALL SELECT 'Crear Gastos', 'expenses.create', 'expenses', 'Registrar gastos'
    UNION ALL SELECT 'Eliminar Gastos', 'expenses.delete', 'expenses', 'Anular gastos'
    UNION ALL SELECT 'Ver Categorías de gasto', 'expense_categories.view', 'expense_categories', 'Consultar las categorías de gasto'
    UNION ALL SELECT 'Crear Categorías de gasto', 'expense_categories.create', 'expense_categories', 'Dar de alta categorías de gasto'
    UNION ALL SELECT 'Editar Categorías de gasto', 'expense_categories.edit', 'expense_categories', 'Modificar las categorías de gasto'
    UNION ALL SELECT 'Eliminar Categorías de gasto', 'expense_categories.delete', 'expense_categories', 'Dar de baja categorías de gasto'
    UNION ALL SELECT 'Ver Proveedores', 'suppliers.view', 'suppliers', 'Consultar los proveedores'
    UNION ALL SELECT 'Crear Proveedores', 'suppliers.create', 'suppliers', 'Dar de alta proveedores'
    UNION ALL SELECT 'Editar Proveedores', 'suppliers.edit', 'suppliers', 'Modificar los proveedores'
    UNION ALL SELECT 'Eliminar Proveedores', 'suppliers.delete', 'suppliers', 'Dar de baja proveedores'
    UNION ALL SELECT 'Ver Compras', 'purchases.view', 'purchases', 'Consultar las compras y lo que se debe a proveedores'
    UNION ALL SELECT 'Crear Compras', 'purchases.create', 'purchases', 'Registrar compras'
    UNION ALL SELECT 'Editar Compras', 'purchases.edit', 'purchases', 'Registrar pagos de compras a crédito'
    UNION ALL SELECT 'Eliminar Compras', 'purchases.delete', 'purchases', 'Anular compras'
) p
WHERE NOT EXISTS (SELECT 1 FROM `permissions` x WHERE x.`slug` = p.`slug`);

-- Todos, a los roles que ya editan la configuración (dueño / administrador).
INSERT INTO `role_permission` (`role_id`, `permission_id`)
SELECT DISTINCT rp.`role_id`, np.`id`
FROM `role_permission` rp
JOIN `permissions` s ON s.`id` = rp.`permission_id` AND s.`slug` = 'settings.edit'
JOIN `permissions` np ON np.`module` IN ('accounts', 'account_movements', 'expenses', 'expense_categories', 'suppliers', 'purchases')
WHERE NOT EXISTS (
    SELECT 1 FROM `role_permission` y WHERE y.`role_id` = rp.`role_id` AND y.`permission_id` = np.`id`
);

-- Consultar y registrar, al rol del sistema «manager» (encargado).
INSERT INTO `role_permission` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
JOIN `permissions` p ON p.`slug` IN (
    'accounts.view', 'account_movements.create', 'expenses.view', 'expenses.create',
    'expense_categories.view', 'suppliers.view', 'suppliers.create', 'suppliers.edit',
    'purchases.view', 'purchases.create', 'purchases.edit'
)
WHERE r.`company_id` IS NULL AND r.`slug` = 'manager' AND r.`deleted_at` IS NULL
  AND NOT EXISTS (SELECT 1 FROM `role_permission` y WHERE y.`role_id` = r.`id` AND y.`permission_id` = p.`id`);

COMMIT;

-- Opcional: añadir el módulo «finanzas» a TODOS los planes de una vez
-- (si prefieres elegir plan por plan, hazlo desde Plataforma → Planes).
-- UPDATE `plans` SET `features` = JSON_ARRAY_APPEND(`features`, '$', 'finanzas')
-- WHERE NOT JSON_CONTAINS(`features`, '"finanzas"');
