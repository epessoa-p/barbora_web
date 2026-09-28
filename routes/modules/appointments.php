<?php

use App\Http\Controllers\Appointments\AgendaBlockController;
use App\Http\Controllers\Appointments\AppointmentController;
use App\Http\Controllers\Appointments\CalendarController;
use App\Http\Controllers\Appointments\ReminderController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Módulo: Citas (agenda)
|--------------------------------------------------------------------------
|
| Requiere la feature «agenda» del plan.
| Controladores: app/Http/Controllers/Appointments/
|
| Se apoya en Servicios (duración y precio), Clientes (a quién se atiende) y
| los horarios del personal (cuándo hay hueco).
|
| Los bloqueos (vacaciones, feriados) van en este mismo módulo pero con sus
| propios permisos: quien agenda no tiene por qué poder declarar un feriado.
|
| Los recordatorios exigen además la feature «reservas_online».
|
*/

Route::middleware('plan:agenda')->group(function () {

    // Bloqueos de agenda. Fuera del prefijo «citas» porque no son citas: son
    // el calendario de cuándo NO se atiende.
    Route::prefix('agenda/bloqueos')->name('agenda-blocks.')->group(function () {
        Route::get('/', [AgendaBlockController::class, 'index'])->middleware('check-permission:agenda_blocks.view')->name('index');
        Route::get('/create', [AgendaBlockController::class, 'create'])->middleware('check-permission:agenda_blocks.create')->name('create');
        Route::post('/', [AgendaBlockController::class, 'store'])->middleware('check-permission:agenda_blocks.create')->name('store');
        Route::get('/{agendaBlock}/edit', [AgendaBlockController::class, 'edit'])->middleware('check-permission:agenda_blocks.edit')->name('edit');
        Route::put('/{agendaBlock}', [AgendaBlockController::class, 'update'])->middleware('check-permission:agenda_blocks.edit')->name('update');
        Route::delete('/{agendaBlock}', [AgendaBlockController::class, 'destroy'])->middleware('check-permission:agenda_blocks.delete')->name('destroy');
    });

    // Recordatorios: además del plan «agenda», exigen «reservas_online».
    Route::prefix('agenda/recordatorios')->name('reminders.')
        ->middleware('plan:reservas_online')
        ->group(function () {
            Route::get('/', [ReminderController::class, 'index'])->middleware('check-permission:appointments.view')->name('index');
            Route::post('/{appointment}', [ReminderController::class, 'store'])->middleware('check-permission:appointments.edit')->name('store');
            Route::delete('/{appointment}', [ReminderController::class, 'destroy'])->middleware('check-permission:appointments.edit')->name('destroy');
        });
});

Route::middleware('plan:agenda')->prefix('citas')->name('appointments.')->group(function () {
    // El calendario va antes que {appointment} para que su ruta literal no la
    // capture el binding.
    Route::get('/calendario', CalendarController::class)
        ->middleware('check-permission:appointments.view')->name('calendar');

    Route::get('/', [AppointmentController::class, 'index'])->middleware('check-permission:appointments.view')->name('index');
    Route::get('/create', [AppointmentController::class, 'create'])->middleware('check-permission:appointments.create')->name('create');
    Route::post('/', [AppointmentController::class, 'store'])->middleware('check-permission:appointments.create')->name('store');
    Route::get('/{appointment}', [AppointmentController::class, 'show'])->middleware('check-permission:appointments.view')->name('show');
    Route::get('/{appointment}/edit', [AppointmentController::class, 'edit'])->middleware('check-permission:appointments.edit')->name('edit');
    Route::put('/{appointment}', [AppointmentController::class, 'update'])->middleware('check-permission:appointments.edit')->name('update');
    Route::patch('/{appointment}/estado', [AppointmentController::class, 'updateStatus'])
        ->middleware('check-permission:appointments.edit')->name('status');
    Route::delete('/{appointment}', [AppointmentController::class, 'destroy'])->middleware('check-permission:appointments.delete')->name('destroy');
});
