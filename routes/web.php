<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SubscriptionBlockedController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas web
|--------------------------------------------------------------------------
|
| Este archivo solo arma el esqueleto: cada módulo declara sus rutas en
| routes/modules/<modulo>.php, junto a sus controladores en
| app/Http/Controllers/<Modulo>/. Al añadir un módulo nuevo se crea su archivo
| y se registra en la lista de abajo; nada más de aquí cambia.
|
| Orden de las capas de autorización en cada petición:
|   auth → set-tenant → subscription → plan:<mod> → check-permission:<p>
|
| `set-tenant` y `subscription` corren en todo el grupo `web`
| (ver bootstrap/app.php); `plan:` y `check-permission:` los aplica cada módulo.
|
*/

require __DIR__.'/auth.php';

Route::middleware('auth')->group(function () {
    // Pantallas transversales, sin módulo propio.
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/suscripcion/bloqueada', SubscriptionBlockedController::class)->name('subscription.blocked');

    foreach ([
        'platform',      // operador del SaaS: empresas, planes, suscripciones, roles
        'organization',  // datos maestros de la empresa: usuarios, cargos, personal, sucursales
        'appointments',  // módulo de plan «agenda»: citas y calendario
        'services',      // módulo de plan «agenda»: catálogo de servicios
        'clients',       // módulo de plan «clientes»: fichas de cliente
        'sales',         // módulo de plan «pos»: ventas y comprobantes
        'commissions',   // módulo de plan «comisiones»: reglas y liquidaciones
        'inventory',     // módulo de plan «inventario»: almacenes
        'cash',          // módulo de plan «caja»: cajas
        'reports',       // módulo de plan «estadisticas»: reportes del negocio
        'settings',      // administrativo: cómo cobra y cómo se identifica la barbería
    ] as $module) {
        require __DIR__."/modules/{$module}.php";
    }
});

Route::redirect('/', '/dashboard');
