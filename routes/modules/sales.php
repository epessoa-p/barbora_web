<?php

use App\Http\Controllers\Sales\SaleController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Módulo: Ventas (POS)
|--------------------------------------------------------------------------
|
| Requiere la feature «pos» del plan.
| Controladores: app/Http/Controllers/Sales/
|
| Es el módulo que cierra el ciclo: cobra la cita, descuenta el stock de los
| productos e ingresa el dinero en el turno de caja abierto. Todo eso vive en
| App\Support\SaleRegistrar, dentro de una única transacción.
|
| Pendiente: devoluciones parciales — ver CHECKLIST.md.
|
*/

Route::middleware('plan:pos')->prefix('ventas')->name('sales.')->group(function () {
    Route::get('/', [SaleController::class, 'index'])->middleware('check-permission:sales.view')->name('index');
    Route::get('/nueva', [SaleController::class, 'create'])->middleware('check-permission:sales.create')->name('create');
    Route::post('/', [SaleController::class, 'store'])->middleware('check-permission:sales.create')->name('store');
    Route::get('/{sale}', [SaleController::class, 'show'])->middleware('check-permission:sales.view')->name('show');
    Route::get('/{sale}/comprobante', [SaleController::class, 'receipt'])->middleware('check-permission:sales.view')->name('receipt');
    Route::patch('/{sale}/anular', [SaleController::class, 'cancel'])->middleware('check-permission:sales.delete')->name('cancel');
});
