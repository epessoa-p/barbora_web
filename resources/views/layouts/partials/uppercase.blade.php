{{--
    Nombres en mayúsculas.

    Los campos de nombre (cargo, personal, cliente, servicio, producto,
    categoría, sucursal, caja, almacén, empresa…) se escriben siempre en
    mayúsculas, en todos los formularios a la vez. Correos, usuario,
    contraseñas, teléfonos, códigos, notas y descripciones quedan como se
    escriben. Un campo concreto se excluye con data-no-upper; uno extra se
    incluye con data-upper.

    Va en línea y no en resources/js/app.js a propósito: lo compilado
    (public/build) no se sube con el código, y así funciona en cuanto se
    despliega, sin tener que recompilar en el servidor.
--}}
<script>
(() => {
    const FIELDS = 'input[name="name"], input[name="full_name"], input[name="manager_name"], input[data-upper]';

    const applies = (el) =>
        el instanceof HTMLInputElement && el.matches(FIELDS) && !el.closest('[data-no-upper]');

    const toUpper = (el) => {
        const upper = el.value.toLocaleUpperCase('es');
        if (upper === el.value) return;

        // Conserva la posición del cursor: si no, salta al final en cada tecla.
        const { selectionStart, selectionEnd } = el;
        el.value = upper;
        if (selectionStart !== null && upper.length === el.value.length) {
            el.setSelectionRange(selectionStart, selectionEnd);
        }
    };

    document.addEventListener('input', (e) => {
        if (applies(e.target)) toUpper(e.target);
    });

    // Al guardar se normalizan también los valores que ya venían en minúsculas
    // (registros antiguos que se editan sin tocar el nombre).
    document.addEventListener('submit', (e) => {
        e.target.querySelectorAll?.(FIELDS).forEach((el) => {
            if (applies(el)) toUpper(el);
        });
    }, true);
})();
</script>
