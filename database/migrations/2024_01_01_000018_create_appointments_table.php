<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Citas de la agenda.
 *
 * Sobre las fechas: se guardan en la hora de pared de la barbería, sin
 * conversión a UTC. Cada empresa opera en una sola zona horaria (la tiene en
 * `companies.timezone`) y todo su equipo está allí, así que convertir solo
 * añadiría ruido: «las 10:00 del martes» significa lo mismo para quien reserva,
 * quien corta y quien cobra. Si algún día una empresa opera en varias zonas,
 * habrá que migrar a UTC y guardar la zona de cada sucursal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('personal_id')->constrained('personal');
            $table->foreignId('client_id')->constrained('clients');

            $table->dateTime('starts_at');
            $table->dateTime('ends_at');

            $table->enum('status', ['reservada', 'confirmada', 'atendida', 'no_show', 'cancelada'])
                  ->default('reservada');

            $table->text('notes')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->softDeletes();
            $table->timestamps();

            // Las dos consultas de la agenda: «qué hay este día» y «está libre
            // este barbero a esta hora».
            $table->index(['company_id', 'starts_at']);
            $table->index(['personal_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
