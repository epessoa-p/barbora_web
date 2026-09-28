<?php

use App\Http\Controllers\Cash\CajaController;
use App\Http\Controllers\Cash\CashMovementController;
use App\Http\Controllers\Cash\CashSessionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Módulo: Caja
|--------------------------------------------------------------------------
|
| Requiere la feature «caja» del plan.
| Controladores: app/Http/Controllers/Cash/
|
| Dos niveles de permiso:
|   cajas.*  — administrar las cajas registradoras (datos maestros)
|   cash.*   — operar el turno: abrir, mover dinero y arquear
|
*/

Route::middleware('plan:caja')->group(function () {

    // Operación diaria del turno.
    Route::prefix('caja')->name('cash.')->group(function () {
        Route::get('/', [CashSessionController::class, 'current'])
            ->middleware('check-permission:cash.view')->name('current');

        Route::post('/abrir', [CashSessionController::class, 'store'])
            ->middleware('check-permission:cash.create')->name('open');

        Route::get('/movimientos', [CashMovementController::class, 'index'])
            ->middleware('check-permission:cash.view')->name('movements');

        Route::post('/turnos/{session}/movimientos', [CashMovementController::class, 'store'])
            ->middleware('check-permission:cash.create')->name('movements.store');

        Route::delete('/movimientos/{movement}', [CashMovementController::class, 'destroy'])
            ->middleware('check-permission:cash.delete')->name('movements.destroy');

        Route::get('/turnos', [CashSessionController::class, 'index'])
            ->middleware('check-permission:cash.view')->name('sessions.index');

        Route::get('/turnos/{session}', [CashSessionController::class, 'show'])
            ->middleware('check-permission:cash.view')->name('sessions.show');

        Route::put('/turnos/{session}/cerrar', [CashSessionController::class, 'close'])
            ->middleware('check-permission:cash.edit')->name('sessions.close');
    });

    // Datos maestros: las cajas registradoras en sí.
    Route::prefix('admin/cajas')->name('cajas.')->group(function () {
        Route::get('/', [CajaController::class, 'index'])->middleware('check-permission:cajas.view')->name('index');
        Route::get('/create', [CajaController::class, 'create'])->middleware('check-permission:cajas.create')->name('create');
        Route::post('/', [CajaController::class, 'store'])->middleware('check-permission:cajas.create')->name('store');
        Route::get('/{caja}/edit', [CajaController::class, 'edit'])->middleware('check-permission:cajas.edit')->name('edit');
        Route::put('/{caja}', [CajaController::class, 'update'])->middleware('check-permission:cajas.edit')->name('update');
        Route::delete('/{caja}', [CajaController::class, 'destroy'])->middleware('check-permission:cajas.delete')->name('destroy');
    });
});
