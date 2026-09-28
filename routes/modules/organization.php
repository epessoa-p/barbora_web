<?php

use App\Http\Controllers\Organization\BranchController;
use App\Http\Controllers\Organization\CargoController;
use App\Http\Controllers\Organization\PersonalController;
use App\Http\Controllers\Organization\UserController;
use App\Http\Controllers\Organization\WorkScheduleController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Módulo: Organización (datos maestros de la empresa)
|--------------------------------------------------------------------------
|
| Usuarios, cargos, personal y sucursales. Son módulos ADMINISTRATIVOS: no
| figuran en Plan::PERMISSION_MODULE_FEATURES, así que están disponibles con
| cualquier plan y se gobiernan solo por permiso.
|
| Controladores: app/Http/Controllers/Organization/
|
*/

Route::prefix('admin/users')->name('users.')->group(function () {
    Route::get('/', [UserController::class, 'index'])->middleware('check-permission:users.view')->name('index');
    Route::get('/create', [UserController::class, 'create'])->middleware('check-permission:users.create')->name('create');
    Route::post('/', [UserController::class, 'store'])->middleware('check-permission:users.create')->name('store');
    Route::get('/{user}', [UserController::class, 'show'])->middleware('check-permission:users.view')->name('show');
    Route::get('/{user}/edit', [UserController::class, 'edit'])->middleware('check-permission:users.edit')->name('edit');
    Route::put('/{user}', [UserController::class, 'update'])->middleware('check-permission:users.edit')->name('update');
    Route::delete('/{user}', [UserController::class, 'destroy'])->middleware('check-permission:users.delete')->name('destroy');
    Route::post('/{user}/assign-role/{company}/{role}', [UserController::class, 'assignRole'])
        ->middleware('check-permission:users.edit')->name('assign-role');
});

Route::prefix('admin/cargos')->name('cargos.')->group(function () {
    Route::get('/', [CargoController::class, 'index'])->middleware('check-permission:cargos.view')->name('index');
    Route::get('/create', [CargoController::class, 'create'])->middleware('check-permission:cargos.create')->name('create');
    Route::post('/', [CargoController::class, 'store'])->middleware('check-permission:cargos.create')->name('store');
    Route::get('/role-permissions/{role}', [CargoController::class, 'rolePermissions'])
        ->middleware('check-permission:cargos.create,cargos.edit')->name('role-permissions');
    Route::get('/{cargo}/edit', [CargoController::class, 'edit'])->middleware('check-permission:cargos.edit')->name('edit');
    Route::put('/{cargo}', [CargoController::class, 'update'])->middleware('check-permission:cargos.edit')->name('update');
    Route::delete('/{cargo}', [CargoController::class, 'destroy'])->middleware('check-permission:cargos.delete')->name('destroy');
});

Route::prefix('admin/personal')->name('personal.')->group(function () {
    Route::get('/', [PersonalController::class, 'index'])->middleware('check-permission:personal.view')->name('index');
    Route::get('/create', [PersonalController::class, 'create'])->middleware('check-permission:personal.create')->name('create');
    Route::post('/', [PersonalController::class, 'store'])->middleware('check-permission:personal.create')->name('store');
    Route::get('/{personal}/edit', [PersonalController::class, 'edit'])->middleware('check-permission:personal.edit')->name('edit');
    Route::put('/{personal}', [PersonalController::class, 'update'])->middleware('check-permission:personal.edit')->name('update');
    Route::delete('/{personal}', [PersonalController::class, 'destroy'])->middleware('check-permission:personal.delete')->name('destroy');
});

// Horarios de trabajo. Cuelgan del personal, pero tienen su propio permiso:
// un encargado puede cuadrar turnos sin poder tocar los datos del empleado.
Route::prefix('admin/horarios')->name('schedules.')->group(function () {
    Route::get('/', [WorkScheduleController::class, 'index'])
        ->middleware('check-permission:schedules.view')->name('index');
    Route::get('/{personal}', [WorkScheduleController::class, 'show'])
        ->middleware('check-permission:schedules.view')->name('show');
    Route::post('/{personal}', [WorkScheduleController::class, 'store'])
        ->middleware('check-permission:schedules.edit')->name('store');
    Route::delete('/{personal}/{schedule}', [WorkScheduleController::class, 'destroy'])
        ->middleware('check-permission:schedules.edit')->name('destroy');
});

Route::prefix('admin/branches')->name('branches.')->group(function () {
    Route::get('/', [BranchController::class, 'index'])->middleware('check-permission:branches.view')->name('index');
    Route::get('/create', [BranchController::class, 'create'])->middleware('check-permission:branches.create')->name('create');
    Route::post('/', [BranchController::class, 'store'])->middleware('check-permission:branches.create')->name('store');
    Route::get('/{branch}', [BranchController::class, 'show'])->middleware('check-permission:branches.view')->name('show');
    Route::get('/{branch}/edit', [BranchController::class, 'edit'])->middleware('check-permission:branches.edit')->name('edit');
    Route::put('/{branch}', [BranchController::class, 'update'])->middleware('check-permission:branches.edit')->name('update');
    Route::delete('/{branch}', [BranchController::class, 'destroy'])->middleware('check-permission:branches.delete')->name('destroy');
});
