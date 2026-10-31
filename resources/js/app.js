import './bootstrap';

import * as bootstrap from 'bootstrap';

// Dropdowns, offcanvas, alerts y tooltips se inicializan solos por data-attributes;
// exponerlo permite además instanciarlos a mano desde una vista concreta.
window.bootstrap = bootstrap;

// Los nombres en mayúsculas NO van aquí: están en línea en
// resources/views/layouts/partials/uppercase.blade.php, para que funcionen sin
// recompilar este archivo en el servidor.
