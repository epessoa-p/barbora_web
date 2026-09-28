<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Liquidación: el pago de las comisiones de un barbero en un periodo.
 *
 * Los importes se guardan, no se recalculan. Igual que el arqueo de caja: una
 * liquidación es el comprobante de lo que se pagó aquel día, y tiene que decir
 * lo mismo dentro de un año aunque las reglas hayan cambiado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('personal_id')->constrained('personal')->cascadeOnDelete();

            $table->string('number', 20);
            $table->date('period_start');
            $table->date('period_end');

            $table->unsignedInteger('entries_count')->default(0);
            $table->decimal('commissions_total', 12, 2)->default(0);
            $table->decimal('tips_total', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);

            $table->dateTime('paid_at');
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cash_session_id')->nullable()->constrained('cash_sessions')->nullOnDelete();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'personal_id', 'period_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_settlements');
    }
};
