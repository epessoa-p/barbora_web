import './bootstrap';

import * as bootstrap from 'bootstrap';

// Dropdowns, offcanvas, alerts y tooltips se inicializan solos por data-attributes;
// exponerlo permite además instanciarlos a mano desde una vista concreta.
window.bootstrap = bootstrap;
