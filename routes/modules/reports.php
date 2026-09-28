<?php

use App\Http\Controllers\Reports\ExportController;
use App\Http\Controllers\Reports\ReportController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Módulo: Reportes
|--------------------------------------------------------------------------
|
| Requiere la feature «estadisticas» del plan.
| Controladores: app/Http/Controllers/Reports/
|
| Todo es de solo lectura y se apoya en App\Support\Reports\*, que consulta
| siempre a través de los modelos para que el CompanyScope siga aplicando.
| El periodo lo interpreta App\Support\ReportPeriod a partir de ?from/?to.
|
*/

Route::middleware(['plan:estadisticas', 'check-permission:reports.view'])
    ->prefix('reportes')
    ->name('reports.')
    ->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('index');
        Route::get('/ventas', [ReportController::class, 'sales'])->name('sales');
        Route::get('/servicios', [ReportController::class, 'services'])->name('services');
        Route::get('/barberos', [ReportController::class, 'staff'])->name('staff');
        Route::get('/productos', [ReportController::class, 'products'])->name('products');
        Route::get('/clientes', [ReportController::class, 'clients'])->name('clients');
        Route::get('/ganancias', [ReportController::class, 'earnings'])->name('earnings');

        Route::get('/{report}/exportar', ExportController::class)
            ->middleware('check-permission:reports.export')
            ->whereIn('report', ['ventas', 'servicios', 'barberos', 'productos', 'clientes', 'ganancias'])
            ->name('export');
    });
