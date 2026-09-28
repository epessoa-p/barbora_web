<?php

use App\Http\Controllers\Platform\CompanyController;
use App\Http\Controllers\Platform\PlanController;
use App\Http\Controllers\Platform\RoleController;
use App\Http\Controllers\Platform\SubscriptionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Módulo: Plataforma (operador del SaaS)
|--------------------------------------------------------------------------
|
| Empresas, planes, suscripciones y el catálogo global de roles. Reservado al
| superadmin, que además salta suscripción, plan, permiso y cupo.
|
| Controladores: app/Http/Controllers/Platform/
|
*/

Route::middleware('check-role:super_admin')->group(function () {

    Route::prefix('admin/companies')->name('companies.')->group(function () {
        Route::get('/', [CompanyController::class, 'index'])->name('index');
        Route::get('/create', [CompanyController::class, 'create'])->name('create');
        Route::post('/', [CompanyController::class, 'store'])->name('store');
        Route::get('/{company}', [CompanyController::class, 'show'])->name('show');
        Route::get('/{company}/edit', [CompanyController::class, 'edit'])->name('edit');
        Route::put('/{company}', [CompanyController::class, 'update'])->name('update');
        Route::delete('/{company}', [CompanyController::class, 'destroy'])->name('destroy');

        // Suscripción de la empresa: plan, estado, fechas y overrides.
        Route::get('/{company}/subscription/edit', [SubscriptionController::class, 'edit'])->name('subscription.edit');
        Route::put('/{company}/subscription', [SubscriptionController::class, 'update'])->name('subscription.update');
    });

    Route::prefix('admin/plans')->name('plans.')->group(function () {
        Route::get('/', [PlanController::class, 'index'])->name('index');
        Route::get('/create', [PlanController::class, 'create'])->name('create');
        Route::post('/', [PlanController::class, 'store'])->name('store');
        Route::get('/{plan}/edit', [PlanController::class, 'edit'])->name('edit');
        Route::put('/{plan}', [PlanController::class, 'update'])->name('update');
        Route::delete('/{plan}', [PlanController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('admin/roles')->name('roles.')->group(function () {
        Route::get('/', [RoleController::class, 'index'])->name('index');
        Route::get('/create', [RoleController::class, 'create'])->name('create');
        Route::post('/', [RoleController::class, 'store'])->name('store');
        Route::get('/{role}', [RoleController::class, 'show'])->name('show');
        Route::get('/{role}/edit', [RoleController::class, 'edit'])->name('edit');
        Route::put('/{role}', [RoleController::class, 'update'])->name('update');
        Route::delete('/{role}', [RoleController::class, 'destroy'])->name('destroy');
    });
});
