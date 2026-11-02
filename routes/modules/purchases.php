<?php

use App\Http\Controllers\Purchases\PurchaseController;
use App\Http\Controllers\Purchases\SupplierController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Módulo: Compras
|--------------------------------------------------------------------------
|
| Las compras requieren «inventario» (la mercadería entra al almacén). Los
| proveedores sirven también a los gastos: basta «inventario» o «finanzas».
| Controladores: app/Http/Controllers/Purchases/
|
|   purchases.edit  = registrar pagos de una compra a crédito
|   purchases.delete = anular
|
*/

Route::middleware('plan:inventario')->prefix('compras')->name('purchases.')->group(function () {
    Route::get('/', [PurchaseController::class, 'index'])->middleware('check-permission:purchases.view')->name('index');
    Route::get('/create', [PurchaseController::class, 'create'])->middleware('check-permission:purchases.create')->name('create');
    Route::post('/', [PurchaseController::class, 'store'])->middleware('check-permission:purchases.create')->name('store');
    Route::get('/{purchase}', [PurchaseController::class, 'show'])->middleware('check-permission:purchases.view')->name('show');
    Route::post('/{purchase}/pagos', [PurchaseController::class, 'pay'])->middleware('check-permission:purchases.edit')->name('pay');
    Route::patch('/{purchase}/anular', [PurchaseController::class, 'cancel'])->middleware('check-permission:purchases.delete')->name('cancel');
});

Route::middleware('plan:inventario,finanzas')->prefix('proveedores')->name('suppliers.')->group(function () {
    Route::get('/', [SupplierController::class, 'index'])->middleware('check-permission:suppliers.view')->name('index');
    Route::get('/create', [SupplierController::class, 'create'])->middleware('check-permission:suppliers.create')->name('create');
    Route::post('/', [SupplierController::class, 'store'])->middleware('check-permission:suppliers.create')->name('store');
    Route::post('/rapido', [SupplierController::class, 'quickStore'])->middleware('check-permission:suppliers.create')->name('quick-store');
    Route::get('/{supplier}', [SupplierController::class, 'show'])->middleware('check-permission:suppliers.view')->name('show');
    Route::get('/{supplier}/edit', [SupplierController::class, 'edit'])->middleware('check-permission:suppliers.edit')->name('edit');
    Route::put('/{supplier}', [SupplierController::class, 'update'])->middleware('check-permission:suppliers.edit')->name('update');
    Route::delete('/{supplier}', [SupplierController::class, 'destroy'])->middleware('check-permission:suppliers.delete')->name('destroy');
});
