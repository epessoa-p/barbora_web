<?php

use App\Http\Controllers\Inventory\ProductCategoryController;
use App\Http\Controllers\Inventory\ProductController;
use App\Http\Controllers\Inventory\StockController;
use App\Http\Controllers\Inventory\WarehouseController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Módulo: Inventario
|--------------------------------------------------------------------------
|
| Requiere la feature «inventario» del plan.
| Controladores: app/Http/Controllers/Inventory/
|
| Todo el stock se mueve por App\Support\StockManager, que escribe el saldo y
| el historial en la misma transacción. Los movimientos son inmutables: un
| error se corrige con un ajuste.
|
| Pendiente: descuento automático al vender — llega con Ventas. Ver CHECKLIST.md.
|
*/

Route::middleware('plan:inventario')->group(function () {

    // Almacenes.
    Route::prefix('inventario/almacenes')->name('warehouses.')->group(function () {
        Route::get('/', [WarehouseController::class, 'index'])->middleware('check-permission:warehouses.view')->name('index');
        Route::get('/create', [WarehouseController::class, 'create'])->middleware('check-permission:warehouses.create')->name('create');
        Route::post('/', [WarehouseController::class, 'store'])->middleware('check-permission:warehouses.create')->name('store');
        Route::get('/{warehouse}', [WarehouseController::class, 'show'])->middleware('check-permission:warehouses.view')->name('show');
        Route::get('/{warehouse}/edit', [WarehouseController::class, 'edit'])->middleware('check-permission:warehouses.edit')->name('edit');
        Route::put('/{warehouse}', [WarehouseController::class, 'update'])->middleware('check-permission:warehouses.edit')->name('update');
        Route::delete('/{warehouse}', [WarehouseController::class, 'destroy'])->middleware('check-permission:warehouses.delete')->name('destroy');
    });

    // Categorías antes que productos: prefijo literal, no lo captura {product}.
    Route::prefix('inventario/categorias')->name('product-categories.')->group(function () {
        Route::get('/', [ProductCategoryController::class, 'index'])->middleware('check-permission:product_categories.view')->name('index');
        Route::get('/create', [ProductCategoryController::class, 'create'])->middleware('check-permission:product_categories.create')->name('create');
        Route::post('/', [ProductCategoryController::class, 'store'])->middleware('check-permission:product_categories.create')->name('store');
        Route::get('/{productCategory}/edit', [ProductCategoryController::class, 'edit'])->middleware('check-permission:product_categories.edit')->name('edit');
        Route::put('/{productCategory}', [ProductCategoryController::class, 'update'])->middleware('check-permission:product_categories.edit')->name('update');
        Route::delete('/{productCategory}', [ProductCategoryController::class, 'destroy'])->middleware('check-permission:product_categories.delete')->name('destroy');
    });

    // Existencias y movimientos.
    Route::prefix('inventario/stock')->name('stock.')->group(function () {
        Route::get('/', [StockController::class, 'index'])->middleware('check-permission:stock.view')->name('index');
        Route::get('/movimientos', [StockController::class, 'movements'])->middleware('check-permission:stock.view')->name('movements');
        Route::get('/nuevo', [StockController::class, 'create'])->middleware('check-permission:stock.create')->name('create');
        Route::post('/', [StockController::class, 'store'])->middleware('check-permission:stock.create')->name('store');
    });

    Route::prefix('inventario/productos')->name('products.')->group(function () {
        Route::get('/', [ProductController::class, 'index'])->middleware('check-permission:products.view')->name('index');
        Route::get('/create', [ProductController::class, 'create'])->middleware('check-permission:products.create')->name('create');
        Route::post('/', [ProductController::class, 'store'])->middleware('check-permission:products.create')->name('store');
        Route::get('/{product}', [ProductController::class, 'show'])->middleware('check-permission:products.view')->name('show');
        Route::get('/{product}/edit', [ProductController::class, 'edit'])->middleware('check-permission:products.edit')->name('edit');
        Route::put('/{product}', [ProductController::class, 'update'])->middleware('check-permission:products.edit')->name('update');
        Route::delete('/{product}', [ProductController::class, 'destroy'])->middleware('check-permission:products.delete')->name('destroy');
    });
});
