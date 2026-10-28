-- ---------------------------------------------------------------------------
-- Nombres en mayúsculas (datos existentes)
--
-- Desde ahora la web y la app escriben los nombres en mayúsculas. Este script
-- pone igual los que ya estaban guardados. Solo datos: no cambia la estructura.
--
-- Seguro de reejecutar: UPPER() sobre algo ya en mayúsculas no cambia nada.
-- La comparación es BINARY porque la intercalación de MySQL no distingue
-- mayúsculas, y sin ella el WHERE no encontraría nada que cambiar.
--
-- No se tocan:
--   - users.name: es el nombre de usuario para iniciar sesión.
--   - Correos, notas, descripciones, códigos ni SKU.
--   - Ventas ya emitidas (sale_items.description): son comprobantes históricos.
-- ---------------------------------------------------------------------------

START TRANSACTION;

UPDATE companies          SET name = UPPER(name)                 WHERE BINARY name <> BINARY UPPER(name);
UPDATE branches           SET name = UPPER(name)                 WHERE BINARY name <> BINARY UPPER(name);
UPDATE branches           SET manager_name = UPPER(manager_name) WHERE manager_name IS NOT NULL AND BINARY manager_name <> BINARY UPPER(manager_name);
UPDATE cargos             SET name = UPPER(name)                 WHERE BINARY name <> BINARY UPPER(name);
UPDATE personal           SET full_name = UPPER(full_name)       WHERE BINARY full_name <> BINARY UPPER(full_name);
UPDATE clients            SET full_name = UPPER(full_name)       WHERE BINARY full_name <> BINARY UPPER(full_name);
UPDATE services           SET name = UPPER(name)                 WHERE BINARY name <> BINARY UPPER(name);
UPDATE service_categories SET name = UPPER(name)                 WHERE BINARY name <> BINARY UPPER(name);
UPDATE products           SET name = UPPER(name)                 WHERE BINARY name <> BINARY UPPER(name);
UPDATE product_categories SET name = UPPER(name)                 WHERE BINARY name <> BINARY UPPER(name);
UPDATE warehouses         SET name = UPPER(name)                 WHERE BINARY name <> BINARY UPPER(name);
UPDATE cajas              SET name = UPPER(name)                 WHERE BINARY name <> BINARY UPPER(name);
UPDATE payment_methods    SET name = UPPER(name)                 WHERE BINARY name <> BINARY UPPER(name);

COMMIT;
