<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Periodos en los que NO se puede reservar: vacaciones de un barbero,
        // un feriado que cierra la barbería entera, el hueco del almuerzo.
        //
        // Es lo contrario de work_schedules: aquel dice cuándo se trabaja de
        // forma recurrente, éste tacha fechas concretas por encima.
        Schema::create('agenda_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();

            // NULL = afecta a TODA la barbería (un feriado).
            $table->foreignId('personal_id')->nullable()->constrained('personal')->cascadeOnDelete();

            // NULL = afecta a todas las sucursales.
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();

            $table->string('title');
            $table->enum('reason', ['vacaciones', 'feriado', 'descanso', 'permiso', 'otro'])->default('otro');

            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            // Un bloqueo de día completo se guarda de 00:00 a 23:59; esto es
            // para pintarlo distinto, no para calcular.
            $table->boolean('all_day')->default(false);

            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['company_id', 'starts_at']);
            $table->index(['personal_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agenda_blocks');
    }
};
