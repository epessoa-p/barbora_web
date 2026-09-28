<?php

use App\Http\Controllers\Api\V1\AgendaController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CashController;
use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\MyController;
use App\Http\Controllers\Api\V1\SaleController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1
|--------------------------------------------------------------------------
|
| Los nombres van sin prefijo aquí: el grupo de routes/api.php les antepone
| «api.». EnsureSubscriptionActive exime a api.login, api.logout, api.me y
| api.companies, para que una barbería con la suscripción vencida pueda al
| menos entrar y ver por qué no puede hacer nada más.
|
*/

// ── Público ─────────────────────────────────────────────────────────────────

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:6,1')
    ->name('login');

// ── Autenticado ─────────────────────────────────────────────────────────────

Route::middleware('auth:sanctum')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Quién soy y qué puedo hacer en la empresa activa: la app arranca con esto.
    Route::get('/me', [AuthController::class, 'me'])->name('me');

    // Empresas a las que pertenezco, para elegir el X-Company-Id.
    Route::get('/companies', [AuthController::class, 'companies'])->name('companies');

    // ── Agenda (módulo de plan «agenda») ────────────────────────────────────
    Route::middleware('plan:agenda')->group(function () {
        Route::get('/agenda', [AgendaController::class, 'index'])
            ->middleware('check-permission:appointments.view')->name('agenda');

        Route::get('/appointments/{appointment}', [AgendaController::class, 'show'])
            ->middleware('check-permission:appointments.view')->name('appointments.show');

        Route::patch('/appointments/{appointment}/status', [AgendaController::class, 'updateStatus'])
            ->middleware('check-permission:appointments.edit')->name('appointments.status');

        Route::get('/services', [CatalogController::class, 'services'])
            ->middleware('check-permission:services.view')->name('services');
    });

    // ── Clientes (módulo de plan «clientes») ────────────────────────────────
    Route::get('/clients', [CatalogController::class, 'clients'])
        ->middleware(['plan:clientes', 'check-permission:clients.view'])
        ->name('clients');

    // ── Productos (módulo de plan «inventario») ─────────────────────────────
    Route::get('/products', [CatalogController::class, 'products'])
        ->middleware(['plan:inventario', 'check-permission:products.view'])
        ->name('products');

    // ── Caja (módulo de plan «caja») ────────────────────────────────────────
    Route::middleware('plan:caja')->prefix('cash')->name('cash.')->group(function () {
        Route::get('/cajas', [CashController::class, 'cajas'])
            ->middleware('check-permission:cash.view')->name('cajas');

        Route::get('/session', [CashController::class, 'current'])
            ->middleware('check-permission:cash.view')->name('session');

        Route::post('/session', [CashController::class, 'open'])
            ->middleware('check-permission:cash.create')->name('open');

        Route::put('/session/{session}/close', [CashController::class, 'close'])
            ->middleware('check-permission:cash.edit')->name('close');

        Route::get('/movements', [CashController::class, 'movements'])
            ->middleware('check-permission:cash.view')->name('movements');

        Route::post('/movements', [CashController::class, 'storeMovement'])
            ->middleware('check-permission:cash.create')->name('movements.store');
    });

    // ── Cobro (módulo de plan «pos») ────────────────────────────────────────
    //
    // El POST lleva idempotencia: la app manda una cabecera «Idempotency-Key»
    // por cobro y un reintento tras perder la red devuelve la venta original
    // en vez de cobrar otra vez. Ver App\Support\Idempotency.
    Route::middleware('plan:pos')->prefix('sales')->name('sales.')->group(function () {
        Route::get('/', [SaleController::class, 'index'])
            ->middleware('check-permission:sales.view')->name('index');

        Route::post('/', [SaleController::class, 'store'])
            ->middleware('check-permission:sales.create')->name('store');

        Route::get('/{sale}', [SaleController::class, 'show'])
            ->middleware('check-permission:sales.view')->name('show');
    });

    // ── Lo mío ──────────────────────────────────────────────────────────────
    // Sin check-permission: son los datos del propio usuario, y el controlador
    // solo devuelve su ficha. Un barbero consulta su horario y sus comisiones
    // sin necesitar permiso sobre los del resto.
    Route::prefix('my')->name('my.')->group(function () {
        Route::get('/schedule', [MyController::class, 'schedule'])
            ->middleware('plan:agenda')->name('schedule');

        Route::get('/agenda', [MyController::class, 'agenda'])
            ->middleware('plan:agenda')->name('agenda');

        Route::get('/commissions', [MyController::class, 'commissions'])
            ->middleware('plan:comisiones')->name('commissions');
    });
});
