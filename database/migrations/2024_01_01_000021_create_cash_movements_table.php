<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ingresos y egresos de un turno de caja.
 *
 * `payment_method` no es decorativo: solo el efectivo está físicamente en el
 * cajón, así que el arqueo se hace únicamente sobre él. Un cobro con tarjeta
 * suma a la recaudación del turno pero no al dinero que se cuenta al cerrar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cash_session_id')->constrained('cash_sessions')->cascadeOnDelete();

            $table->enum('type', ['ingreso', 'egreso']);
            $table->enum('payment_method', ['efectivo', 'tarjeta', 'transferencia', 'qr'])->default('efectivo');

            $table->string('concept');
            $table->decimal('amount', 15, 2);
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->softDeletes();          // anular deja rastro, no borra el turno
            $table->timestamps();

            $table->index(['cash_session_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_movements');
    }
};
