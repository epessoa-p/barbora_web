<?php

use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Autenticación y empresa activa
|--------------------------------------------------------------------------
|
| Estas rutas están exentas del corte por suscripción (ver el array $except de
| EnsureSubscriptionActive): si no, un usuario con la suscripción vencida no
| podría ni salir ni cambiar de empresa.
|
*/

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    Route::get('/select-company', [LoginController::class, 'selectCompany'])->name('select-company');
    Route::post('/set-company/{companyId}', [LoginController::class, 'setCompany'])->name('set-company');
    Route::post('/exit-company', [LoginController::class, 'exitCompany'])->name('exit-company');
});
