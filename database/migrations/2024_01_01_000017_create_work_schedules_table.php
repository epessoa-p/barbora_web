<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Horario semanal de trabajo de cada miembro del personal.
 *
 * Una fila es una franja de un día: «lunes de 09:00 a 13:00». Un turno partido
 * son dos filas del mismo día, por eso no hay índice único por (personal, día):
 * el solape se valida en el controlador, que es donde se puede explicar el
 * porqué al usuario.
 *
 * `branch_id` nulo significa «en cualquier sucursal», que es lo habitual en una
 * barbería de un solo local.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('personal_id')->constrained('personal')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->cascadeOnDelete();

            // ISO-8601: 1 = lunes … 7 = domingo. Coincide con Carbon::dayOfWeekIso.
            $table->unsignedTinyInteger('weekday');
            $table->time('start_time');
            $table->time('end_time');

            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['company_id', 'personal_id', 'weekday']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_schedules');
    }
};
