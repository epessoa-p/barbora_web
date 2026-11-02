<?php

use App\Http\Controllers\Api\V1\AdminController;
use App\Http\Controllers\Api\V1\AgendaBlockController;
use App\Http\Controllers\Api\V1\AgendaController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BookingController;
use App\Http\Controllers\Api\V1\CashController;
use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\FinanceController;
use App\Http\Controllers\Api\V1\InventoryController;
use App\Http\Controllers\Api\V1\MyController;
use App\Http\Controllers\Api\V1\PersonalController;
use App\Http\Controllers\Api\V1\PurchaseController;
use App\Http\Controllers\Api\V1\ReminderController;
use App\Http\Controllers\Api\V1\ReportController;
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

    // El Inicio de la app (resumen del día). Cada bloque comprueba además su
    // módulo y su permiso: ver Api\V1\DashboardController.
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->middleware('check-permission:dashboard.view')->name('dashboard');

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

        Route::get('/service-categories', [CatalogController::class, 'serviceCategories'])
            ->middleware('check-permission:services.view')->name('service-categories');

        // Administrar categorías (también las inactivas), como en la web.
        Route::get('/service-categories/manage', [CatalogController::class, 'manageCategories'])
            ->middleware('check-permission:service_categories.view')->name('service-categories.manage');
        Route::post('/service-categories', [CatalogController::class, 'storeCategory'])
            ->middleware('check-permission:service_categories.create')->name('service-categories.store');
        Route::put('/service-categories/{serviceCategory}', [CatalogController::class, 'updateCategory'])
            ->middleware('check-permission:service_categories.edit')->name('service-categories.update');

        // Editar un servicio (precio, duración, nombre…) desde el móvil.
        Route::put('/services/{service}', [CatalogController::class, 'updateService'])
            ->middleware('check-permission:services.edit')->name('services.update');

        Route::post('/services', [CatalogController::class, 'storeService'])
            ->middleware('check-permission:services.create')->name('services.store');

        // Reservar: barberos, huecos libres y alta de la cita. Las reglas son
        // las de la web (App\Support\AppointmentBooker).
        Route::get('/staff', [BookingController::class, 'staff'])
            ->middleware('check-permission:appointments.create')->name('staff');

        // El mismo catálogo que /services, pero con el permiso de reservar:
        // recepción reserva sin necesitar permiso para ver el catálogo.
        Route::get('/booking/services', [CatalogController::class, 'services'])
            ->middleware('check-permission:appointments.create')->name('booking.services');

        Route::get('/booking/slots', [BookingController::class, 'slots'])
            ->middleware('check-permission:appointments.create')->name('booking.slots');

        Route::post('/appointments', [BookingController::class, 'store'])
            ->middleware('check-permission:appointments.create')->name('appointments.store');

        // Reprogramar: mover día, hora, barbero o servicios de una cita viva.
        Route::put('/appointments/{appointment}', [BookingController::class, 'update'])
            ->middleware('check-permission:appointments.edit')->name('appointments.update');

        // Bloqueos: vacaciones, feriados, descansos. Mismas reglas que la web
        // (App\Support\AgendaBlockForm).
        Route::get('/agenda-blocks', [AgendaBlockController::class, 'index'])
            ->middleware('check-permission:agenda_blocks.view')->name('agenda-blocks');
        Route::post('/agenda-blocks', [AgendaBlockController::class, 'store'])
            ->middleware('check-permission:agenda_blocks.create')->name('agenda-blocks.store');
        Route::put('/agenda-blocks/{agendaBlock}', [AgendaBlockController::class, 'update'])
            ->middleware('check-permission:agenda_blocks.edit')->name('agenda-blocks.update');
        Route::delete('/agenda-blocks/{agendaBlock}', [AgendaBlockController::class, 'destroy'])
            ->middleware('check-permission:agenda_blocks.delete')->name('agenda-blocks.destroy');

        // Recordatorios: como en la web, además exigen «reservas_online».
        Route::middleware('plan:reservas_online')->group(function () {
            Route::get('/reminders', [ReminderController::class, 'index'])
                ->middleware('check-permission:appointments.view')->name('reminders');
            Route::post('/reminders/{appointment}', [ReminderController::class, 'store'])
                ->middleware('check-permission:appointments.edit')->name('reminders.store');
            Route::delete('/reminders/{appointment}', [ReminderController::class, 'destroy'])
                ->middleware('check-permission:appointments.edit')->name('reminders.destroy');
        });
    });

    // ── Barberos (personal) ─────────────────────────────────────────────────
    // Administrativo: sin plan, solo permiso. La cuenta de acceso (usuario) se
    // sigue gestionando en la web; aquí se administra la ficha del barbero.
    Route::get('/personal', [PersonalController::class, 'index'])
        ->middleware('check-permission:personal.view')->name('personal');
    Route::get('/cargos', [PersonalController::class, 'cargos'])
        ->middleware('check-permission:personal.view')->name('cargos');
    // Cargos y sucursales para el formulario (la app vieja sigue con /cargos).
    Route::get('/personal/options', [PersonalController::class, 'options'])
        ->middleware('check-permission:personal.view')->name('personal.options');
    Route::post('/personal', [PersonalController::class, 'store'])
        ->middleware('check-permission:personal.create')->name('personal.store');
    Route::put('/personal/{personal}', [PersonalController::class, 'update'])
        ->middleware('check-permission:personal.edit')->name('personal.update');

    // Alta rápida de cliente al reservar (módulo «clientes»).
    Route::post('/clients', [BookingController::class, 'storeClient'])
        ->middleware(['plan:clientes', 'check-permission:clients.create'])
        ->name('clients.store');

    // ── Clientes (módulo de plan «clientes») ────────────────────────────────
    Route::get('/clients', [CatalogController::class, 'clients'])
        ->middleware(['plan:clientes', 'check-permission:clients.view'])
        ->name('clients');

    // La ficha: datos del cliente, su resumen (visitas, gasto) y últimas citas.
    Route::get('/clients/{client}', [CatalogController::class, 'show'])
        ->middleware(['plan:clientes', 'check-permission:clients.view'])
        ->name('clients.show');

    // Editar la ficha (mismas reglas que la web: App\Support\ClientForm).
    Route::put('/clients/{client}', [CatalogController::class, 'updateClient'])
        ->middleware(['plan:clientes', 'check-permission:clients.edit'])
        ->name('clients.update');

    // ── Productos (módulo de plan «inventario») ─────────────────────────────
    Route::get('/products', [CatalogController::class, 'products'])
        ->middleware(['plan:inventario', 'check-permission:products.view'])
        ->name('products');

    // ── Inventario (módulo de plan «inventario») ────────────────────────────
    // Gestión completa: todos los productos, existencias y movimientos. Mismas
    // reglas que la web (ProductForm, StockMovementForm).
    Route::middleware('plan:inventario')->prefix('inventory')->name('inventory.')->group(function () {
        Route::get('/options', [InventoryController::class, 'options'])
            ->middleware('check-permission:products.view')->name('options');
        Route::get('/products', [InventoryController::class, 'index'])
            ->middleware('check-permission:products.view')->name('products');
        Route::post('/products', [InventoryController::class, 'store'])
            ->middleware('check-permission:products.create')->name('products.store');
        Route::get('/products/{product}', [InventoryController::class, 'show'])
            ->middleware('check-permission:products.view')->name('products.show');
        Route::put('/products/{product}', [InventoryController::class, 'update'])
            ->middleware('check-permission:products.edit')->name('products.update');
        Route::post('/movements', [InventoryController::class, 'storeMovement'])
            ->middleware('check-permission:stock.create')->name('movements.store');
    });

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

        // Historial de turnos y el detalle de uno (con su arqueo).
        Route::get('/sessions', [CashController::class, 'sessions'])
            ->middleware('check-permission:cash.view')->name('sessions');
        Route::get('/sessions/{session}', [CashController::class, 'showSession'])
            ->middleware('check-permission:cash.view')->name('sessions.show');

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

        // Anular: mismo efecto que en la web (devuelve stock, egreso en caja,
        // revierte comisiones pendientes). Ver SaleRegistrar::cancel.
        Route::patch('/{sale}/cancel', [SaleController::class, 'cancel'])
            ->middleware('check-permission:sales.delete')->name('cancel');
    });

    // ── Reportes (módulo de plan «estadisticas») ────────────────────────────
    // Reusan el mismo cálculo que la web; el móvil los pinta con una pantalla.
    Route::get('/reports/{type}', [ReportController::class, 'show'])
        ->middleware(['plan:estadisticas', 'check-permission:reports.view'])
        ->whereIn('type', array_keys(ReportController::TYPES))
        ->name('reports.show');

    // ── Administración ──────────────────────────────────────────────────────
    // Mismos permisos que la web. Mi empresa y métodos de pago son de
    // «settings» (sin plan, como en la web); las cajas, del módulo «caja».
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/company', [AdminController::class, 'company'])
            ->middleware('check-permission:settings.view')->name('company');
        Route::put('/company', [AdminController::class, 'updateCompany'])
            ->middleware('check-permission:settings.edit')->name('company.update');
        // Multipart (POST: PHP no lee archivos en un PUT).
        Route::post('/company/logo', [AdminController::class, 'updateLogo'])
            ->middleware('check-permission:settings.edit')->name('company.logo');

        Route::get('/branches', [AdminController::class, 'branches'])
            ->middleware('check-permission:branches.view')->name('branches');
        Route::post('/branches', [AdminController::class, 'storeBranch'])
            ->middleware('check-permission:branches.create')->name('branches.store');
        Route::put('/branches/{branch}', [AdminController::class, 'updateBranch'])
            ->middleware('check-permission:branches.edit')->name('branches.update');

        Route::middleware('plan:caja')->group(function () {
            Route::get('/cajas', [AdminController::class, 'cajas'])
                ->middleware('check-permission:cajas.view')->name('cajas');
            Route::get('/cajas/options', [AdminController::class, 'cajaOptions'])
                ->middleware('check-permission:cajas.view')->name('cajas.options');
            Route::post('/cajas', [AdminController::class, 'storeCaja'])
                ->middleware('check-permission:cajas.create')->name('cajas.store');
            Route::put('/cajas/{caja}', [AdminController::class, 'updateCaja'])
                ->middleware('check-permission:cajas.edit')->name('cajas.update');
        });

        // Catálogo público: publicarlo, qué muestra y su enlace / QR / afiche.
        Route::get('/catalog', [AdminController::class, 'catalog'])
            ->middleware('check-permission:settings.view')->name('catalog');
        Route::put('/catalog', [AdminController::class, 'updateCatalog'])
            ->middleware('check-permission:settings.edit')->name('catalog.update');
        Route::post('/catalog/regenerate', [AdminController::class, 'regenerateCatalog'])
            ->middleware('check-permission:settings.edit')->name('catalog.regenerate');

        Route::get('/payment-methods', [AdminController::class, 'paymentMethods'])
            ->middleware('check-permission:settings.view')->name('payment-methods');
        Route::post('/payment-methods', [AdminController::class, 'storePaymentMethod'])
            ->middleware('check-permission:settings.edit')->name('payment-methods.store');
        Route::put('/payment-methods/{paymentMethod}', [AdminController::class, 'updatePaymentMethod'])
            ->middleware('check-permission:settings.edit')->name('payment-methods.update');
    });

    // ── Finanzas (módulo de plan «finanzas») ────────────────────────────────
    // Cuentas, sus movimientos, gastos y categorías. Mismas reglas que la web.
    Route::middleware('plan:finanzas')->prefix('finance')->name('finance.')->group(function () {
        Route::get('/options', [FinanceController::class, 'options'])
            ->middleware('check-permission:expenses.create')->name('options');

        Route::get('/accounts', [FinanceController::class, 'accounts'])
            ->middleware('check-permission:accounts.view')->name('accounts');
        Route::post('/accounts', [FinanceController::class, 'storeAccount'])
            ->middleware('check-permission:accounts.create')->name('accounts.store');
        Route::put('/accounts/{account}', [FinanceController::class, 'updateAccount'])
            ->middleware('check-permission:accounts.edit')->name('accounts.update');
        Route::get('/accounts/{account}/movements', [FinanceController::class, 'movements'])
            ->middleware('check-permission:accounts.view')->name('accounts.movements');
        Route::post('/accounts/{account}/movements', [FinanceController::class, 'storeMovement'])
            ->middleware('check-permission:account_movements.create')->name('accounts.movements.store');
        Route::post('/accounts/{account}/transfer', [FinanceController::class, 'transfer'])
            ->middleware('check-permission:account_movements.create')->name('accounts.transfer');
        Route::patch('/movements/{movement}/cancel', [FinanceController::class, 'cancelMovement'])
            ->middleware('check-permission:account_movements.delete')->name('movements.cancel');

        Route::get('/expenses', [FinanceController::class, 'expenses'])
            ->middleware('check-permission:expenses.view')->name('expenses');
        Route::post('/expenses', [FinanceController::class, 'storeExpense'])
            ->middleware('check-permission:expenses.create')->name('expenses.store');
        Route::patch('/expenses/{expense}/cancel', [FinanceController::class, 'cancelExpense'])
            ->middleware('check-permission:expenses.delete')->name('expenses.cancel');

        Route::get('/expense-categories', [FinanceController::class, 'categories'])
            ->middleware('check-permission:expense_categories.view')->name('categories');
        Route::post('/expense-categories', [FinanceController::class, 'storeCategory'])
            ->middleware('check-permission:expense_categories.create')->name('categories.store');
        Route::put('/expense-categories/{expenseCategory}', [FinanceController::class, 'updateCategory'])
            ->middleware('check-permission:expense_categories.edit')->name('categories.update');
    });

    // ── Compras (módulo de plan «inventario») y proveedores ─────────────────
    Route::middleware('plan:inventario')->prefix('purchases')->name('purchases.')->group(function () {
        Route::get('/', [PurchaseController::class, 'index'])
            ->middleware('check-permission:purchases.view')->name('index');
        Route::get('/options', [PurchaseController::class, 'options'])
            ->middleware('check-permission:purchases.create')->name('options');
        Route::post('/', [PurchaseController::class, 'store'])
            ->middleware('check-permission:purchases.create')->name('store');
        Route::get('/{purchase}', [PurchaseController::class, 'show'])
            ->middleware('check-permission:purchases.view')->name('show');
        Route::post('/{purchase}/payments', [PurchaseController::class, 'pay'])
            ->middleware('check-permission:purchases.edit')->name('pay');
        Route::patch('/{purchase}/cancel', [PurchaseController::class, 'cancel'])
            ->middleware('check-permission:purchases.delete')->name('cancel');
    });

    Route::middleware('plan:inventario,finanzas')->prefix('suppliers')->name('suppliers.')->group(function () {
        Route::get('/', [PurchaseController::class, 'suppliers'])
            ->middleware('check-permission:suppliers.view')->name('index');
        Route::post('/', [PurchaseController::class, 'storeSupplier'])
            ->middleware('check-permission:suppliers.create')->name('store');
        Route::put('/{supplier}', [PurchaseController::class, 'updateSupplier'])
            ->middleware('check-permission:suppliers.edit')->name('update');
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
