<?php

use App\Http\Controllers\Clients\ClientController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Módulo: Clientes
|--------------------------------------------------------------------------
|
| Ficha del cliente: contacto, cumpleaños, preferencias y alergias. Es la base
| de la agenda (a quién se atiende), del cobro y de la fidelización.
|
| Requiere la feature «clientes» del plan.
| Controladores: app/Http/Controllers/Clients/
|
| Pendiente: historial de atenciones — llega con Citas y Ventas. Ver CHECKLIST.md.
|
*/

Route::middleware('plan:clientes')->prefix('clientes')->name('clients.')->group(function () {
    Route::get('/', [ClientController::class, 'index'])->middleware('check-permission:clients.view')->name('index');
    Route::get('/create', [ClientController::class, 'create'])->middleware('check-permission:clients.create')->name('create');
    Route::post('/', [ClientController::class, 'store'])->middleware('check-permission:clients.create')->name('store');
    Route::get('/{client}', [ClientController::class, 'show'])->middleware('check-permission:clients.view')->name('show');
    Route::get('/{client}/edit', [ClientController::class, 'edit'])->middleware('check-permission:clients.edit')->name('edit');
    Route::put('/{client}', [ClientController::class, 'update'])->middleware('check-permission:clients.edit')->name('update');
    Route::delete('/{client}', [ClientController::class, 'destroy'])->middleware('check-permission:clients.delete')->name('destroy');
});
