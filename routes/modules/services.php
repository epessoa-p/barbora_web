<?php

use App\Http\Controllers\Services\ServiceCategoryController;
use App\Http\Controllers\Services\ServiceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Módulo: Servicios
|--------------------------------------------------------------------------
|
| Catálogo de lo que ofrece la barbería: qué se hace, cuánto dura y cuánto
| cuesta. Es la base de la agenda (la duración reserva el hueco) y de la
| comanda (el precio se cobra).
|
| Requiere la feature «agenda» del plan.
| Controladores: app/Http/Controllers/Services/
|
| Pendiente: precios por sucursal o por barbero, y paquetes de servicios
| — ver CHECKLIST.md.
|
*/

Route::middleware('plan:agenda')->group(function () {

    // Las categorías van primero: su prefijo es literal y así no lo captura
    // un futuro `servicios/{service}`.
    Route::prefix('servicios/categorias')->name('service-categories.')->group(function () {
        Route::get('/', [ServiceCategoryController::class, 'index'])->middleware('check-permission:service_categories.view')->name('index');
        Route::get('/create', [ServiceCategoryController::class, 'create'])->middleware('check-permission:service_categories.create')->name('create');
        Route::post('/', [ServiceCategoryController::class, 'store'])->middleware('check-permission:service_categories.create')->name('store');
        Route::get('/{serviceCategory}/edit', [ServiceCategoryController::class, 'edit'])->middleware('check-permission:service_categories.edit')->name('edit');
        Route::put('/{serviceCategory}', [ServiceCategoryController::class, 'update'])->middleware('check-permission:service_categories.edit')->name('update');
        Route::delete('/{serviceCategory}', [ServiceCategoryController::class, 'destroy'])->middleware('check-permission:service_categories.delete')->name('destroy');
    });

    Route::prefix('servicios')->name('services.')->group(function () {
        Route::get('/', [ServiceController::class, 'index'])->middleware('check-permission:services.view')->name('index');
        Route::get('/create', [ServiceController::class, 'create'])->middleware('check-permission:services.create')->name('create');
        Route::post('/', [ServiceController::class, 'store'])->middleware('check-permission:services.create')->name('store');
        Route::get('/{service}/edit', [ServiceController::class, 'edit'])->middleware('check-permission:services.edit')->name('edit');
        Route::put('/{service}', [ServiceController::class, 'update'])->middleware('check-permission:services.edit')->name('update');
        Route::delete('/{service}', [ServiceController::class, 'destroy'])->middleware('check-permission:services.delete')->name('destroy');
    });
});
