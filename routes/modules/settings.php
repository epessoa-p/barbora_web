<?php

use App\Http\Controllers\Settings\CatalogSettingsController;
use App\Http\Controllers\Settings\CompanyProfileController;
use App\Http\Controllers\Settings\PaymentMethodController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Módulo: Configuración
|--------------------------------------------------------------------------
|
| Ajustes de la propia barbería. Es ADMINISTRATIVO: no depende de ningún
| módulo del plan, porque una barbería tiene que poder configurar cómo cobra
| aunque solo haya contratado la agenda.
|
| Controladores: app/Http/Controllers/Settings/
|
*/

Route::prefix('configuracion')->group(function () {

    // Datos de la barbería que salen en el comprobante. Sin {company} en la
    // ruta: siempre es la empresa activa.
    Route::get('/barberia', [CompanyProfileController::class, 'edit'])
        ->middleware('check-permission:settings.view')->name('company-profile.edit');

    Route::put('/barberia', [CompanyProfileController::class, 'update'])
        ->middleware('check-permission:settings.edit')->name('company-profile.update');

    // Catálogo público: publicarlo, qué muestra, y su enlace / QR / afiche.
    Route::get('/catalogo', [CatalogSettingsController::class, 'edit'])
        ->middleware('check-permission:settings.view')->name('catalog-settings.edit');
    Route::put('/catalogo', [CatalogSettingsController::class, 'update'])
        ->middleware('check-permission:settings.edit')->name('catalog-settings.update');
    Route::post('/catalogo/regenerar', [CatalogSettingsController::class, 'regenerate'])
        ->middleware('check-permission:settings.edit')->name('catalog-settings.regenerate');

    Route::prefix('metodos-pago')->name('payment-methods.')->group(function () {
        Route::get('/', [PaymentMethodController::class, 'index'])
            ->middleware('check-permission:settings.view')->name('index');

        Route::get('/create', [PaymentMethodController::class, 'create'])
            ->middleware('check-permission:settings.edit')->name('create');

        Route::post('/', [PaymentMethodController::class, 'store'])
            ->middleware('check-permission:settings.edit')->name('store');

        Route::get('/{paymentMethod}/edit', [PaymentMethodController::class, 'edit'])
            ->middleware('check-permission:settings.edit')->name('edit');

        Route::put('/{paymentMethod}', [PaymentMethodController::class, 'update'])
            ->middleware('check-permission:settings.edit')->name('update');

        Route::delete('/{paymentMethod}', [PaymentMethodController::class, 'destroy'])
            ->middleware('check-permission:settings.edit')->name('destroy');
    });
});
