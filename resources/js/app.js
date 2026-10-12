import './bootstrap';

import * as bootstrap from 'bootstrap';

// Dropdowns, offcanvas, alerts y tooltips se inicializan solos por data-attributes;
// exponerlo permite además instanciarlos a mano desde una vista concreta.
window.bootstrap = bootstrap;

/*
 * Nombres en mayúsculas.
 *
 * Los campos de nombre (cargo, personal, cliente, servicio, producto…) se
 * escriben siempre en mayúsculas, en todos los formularios a la vez. Correos,
 * usuario, contraseñas, notas y descripciones quedan como se escriben.
 * Un campo concreto se excluye con data-no-upper.
 */
const UPPER_FIELDS = 'input[name="name"], input[name="full_name"], input[name="manager_name"], input[data-upper]';

const isUpperField = (el) =>
    el instanceof HTMLInputElement && el.matches(UPPER_FIELDS) && !el.closest('[data-no-upper]');

const toUpper = (el) => {
    const upper = el.value.toLocaleUpperCase('es');

    if (upper === el.value) return;

    // Conserva la posición del cursor: si no, salta al final en cada tecla.
    const { selectionStart, selectionEnd } = el;
    el.value = upper;
    if (selectionStart !== null) el.setSelectionRange(selectionStart, selectionEnd);
};

document.addEventListener('input', (e) => {
    if (isUpperField(e.target)) toUpper(e.target);
});

// Al guardar se normalizan también los valores que ya venían en minúsculas
// (registros antiguos que se editan sin tocar el nombre).
document.addEventListener('submit', (e) => {
    e.target.querySelectorAll?.(UPPER_FIELDS).forEach((el) => {
        if (isUpperField(el)) toUpper(el);
    });
}, true);
