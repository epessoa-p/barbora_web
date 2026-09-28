<?php

use App\Http\Controllers\Commissions\CommissionController;
use App\Http\Controllers\Commissions\CommissionRuleController;
use App\Http\Controllers\Commissions\SettlementController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Módulo: Comisiones
|--------------------------------------------------------------------------
|
| Requiere la feature «comisiones» del plan.
| Controladores: app/Http/Controllers/Commissions/
|
| Las comisiones se devengan al cobrar (App\Support\CommissionCalculator, desde
| SaleRegistrar) con la regla vigente ese día, y se pagan por periodo en una
| liquidación que sale de la caja.
|
*/

Route::middleware('plan:comisiones')->prefix('comisiones')->group(function () {

    // Las reglas y las liquidaciones van antes que {personal}: prefijos
    // literales que un binding no debe capturar.
    Route::prefix('reglas')->name('commission-rules.')->group(function () {
        Route::get('/', [CommissionRuleController::class, 'index'])->middleware('check-permission:commission_rules.view')->name('index');
        Route::get('/create', [CommissionRuleController::class, 'create'])->middleware('check-permission:commission_rules.create')->name('create');
        Route::post('/', [CommissionRuleController::class, 'store'])->middleware('check-permission:commission_rules.create')->name('store');
        Route::get('/{commissionRule}/edit', [CommissionRuleController::class, 'edit'])->middleware('check-permission:commission_rules.edit')->name('edit');
        Route::put('/{commissionRule}', [CommissionRuleController::class, 'update'])->middleware('check-permission:commission_rules.edit')->name('update');
        Route::delete('/{commissionRule}', [CommissionRuleController::class, 'destroy'])->middleware('check-permission:commission_rules.delete')->name('destroy');
    });

    Route::prefix('liquidaciones')->name('commissions.settlements.')->group(function () {
        Route::get('/', [SettlementController::class, 'index'])->middleware('check-permission:commissions.view')->name('index');
        Route::get('/{settlement}', [SettlementController::class, 'show'])->middleware('check-permission:commissions.view')->name('show');
    });

    Route::name('commissions.')->group(function () {
        Route::get('/', [CommissionController::class, 'index'])->middleware('check-permission:commissions.view')->name('index');
        Route::get('/{personal}', [CommissionController::class, 'show'])->middleware('check-permission:commissions.view')->name('show');
        Route::post('/{personal}/liquidar', [CommissionController::class, 'settle'])->middleware('check-permission:commissions.create')->name('settle');
    });
});
