<?php

use App\Http\Controllers\Finance\AccountController;
use App\Http\Controllers\Finance\AccountMovementController;
use App\Http\Controllers\Finance\ExpenseCategoryController;
use App\Http\Controllers\Finance\ExpenseController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Módulo: Finanzas
|--------------------------------------------------------------------------
|
| Requiere la feature «finanzas» del plan.
| Controladores: app/Http/Controllers/Finance/
|
|   accounts.*            las cuentas (capital, caja chica, banco…)
|   account_movements.*   ingresos, egresos y transferencias entre cuentas
|   expenses.*            los gastos (salen de una cuenta o de la caja abierta)
|   expense_categories.*  en qué se gasta
|
*/

Route::middleware('plan:finanzas')->prefix('finanzas')->group(function () {

    Route::prefix('cuentas')->name('accounts.')->group(function () {
        Route::get('/', [AccountController::class, 'index'])->middleware('check-permission:accounts.view')->name('index');
        Route::get('/create', [AccountController::class, 'create'])->middleware('check-permission:accounts.create')->name('create');
        Route::post('/', [AccountController::class, 'store'])->middleware('check-permission:accounts.create')->name('store');
        Route::get('/{account}', [AccountController::class, 'show'])->middleware('check-permission:accounts.view')->name('show');
        Route::get('/{account}/edit', [AccountController::class, 'edit'])->middleware('check-permission:accounts.edit')->name('edit');
        Route::put('/{account}', [AccountController::class, 'update'])->middleware('check-permission:accounts.edit')->name('update');
        Route::delete('/{account}', [AccountController::class, 'destroy'])->middleware('check-permission:accounts.delete')->name('destroy');

        Route::post('/{account}/movimientos', [AccountMovementController::class, 'store'])
            ->middleware('check-permission:account_movements.create')->name('movements.store');
        Route::post('/{account}/transferir', [AccountMovementController::class, 'transfer'])
            ->middleware('check-permission:account_movements.create')->name('transfer');
        Route::patch('/movimientos/{movement}/anular', [AccountMovementController::class, 'cancel'])
            ->middleware('check-permission:account_movements.delete')->name('movements.cancel');
    });

    Route::prefix('gastos')->name('expenses.')->group(function () {
        Route::get('/', [ExpenseController::class, 'index'])->middleware('check-permission:expenses.view')->name('index');
        Route::get('/create', [ExpenseController::class, 'create'])->middleware('check-permission:expenses.create')->name('create');
        Route::post('/', [ExpenseController::class, 'store'])->middleware('check-permission:expenses.create')->name('store');
        Route::get('/{expense}', [ExpenseController::class, 'show'])->middleware('check-permission:expenses.view')->name('show');
        Route::patch('/{expense}/anular', [ExpenseController::class, 'cancel'])->middleware('check-permission:expenses.delete')->name('cancel');
    });

    Route::prefix('categorias-gasto')->name('expense-categories.')->group(function () {
        Route::get('/', [ExpenseCategoryController::class, 'index'])->middleware('check-permission:expense_categories.view')->name('index');
        Route::post('/', [ExpenseCategoryController::class, 'store'])->middleware('check-permission:expense_categories.create')->name('store');
        Route::put('/{expenseCategory}', [ExpenseCategoryController::class, 'update'])->middleware('check-permission:expense_categories.edit')->name('update');
        Route::delete('/{expenseCategory}', [ExpenseCategoryController::class, 'destroy'])->middleware('check-permission:expense_categories.delete')->name('destroy');
    });
});
