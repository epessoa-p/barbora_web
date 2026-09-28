<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pagos de una venta. Varias filas permiten el pago mixto: parte en efectivo y
 * parte con tarjeta es lo habitual en cuanto el ticket sube.
 *
 * El método importa más allá del cobro: solo el efectivo entra en el arqueo de
 * caja, así que cada pago genera su propio movimiento con su método.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();

            $table->enum('payment_method', ['efectivo', 'tarjeta', 'transferencia', 'qr'])->default('efectivo');
            $table->decimal('amount', 12, 2);
            $table->string('reference')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_payments');
    }
};
