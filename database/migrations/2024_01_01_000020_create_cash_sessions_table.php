<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Turnos de caja: desde que se abre con un fondo hasta que se arquea y cierra.
 *
 * El arqueo guarda las tres cifras por separado a propósito: lo que debería
 * haber (`expected_amount`), lo que se contó (`closing_amount`) y la diferencia.
 * Recalcularlas después daría otro resultado —los movimientos se pueden anular—
 * y el valor de un arqueo es justamente ser una foto de ese momento.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('caja_id')->constrained('cajas')->cascadeOnDelete();

            $table->enum('status', ['abierta', 'cerrada'])->default('abierta');

            $table->dateTime('opened_at');
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('opening_amount', 15, 2)->default(0);
            $table->text('opening_notes')->nullable();

            $table->dateTime('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('closing_amount', 15, 2)->nullable();    // efectivo contado
            $table->decimal('expected_amount', 15, 2)->nullable();   // efectivo que debería haber
            $table->decimal('difference', 15, 2)->nullable();        // contado - esperado
            $table->text('closing_notes')->nullable();

            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['caja_id', 'opened_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_sessions');
    }
};
