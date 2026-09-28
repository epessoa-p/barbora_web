<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ventas: la comanda que cierra el ciclo.
 *
 * Una venta toca tres módulos a la vez: descuenta stock de los productos,
 * registra el ingreso en el turno de caja abierto y, si nace de una cita, la
 * marca como atendida. Todo eso ocurre en una sola transacción
 * (App\Support\SaleRegistrar) para que no pueda quedar a medias.
 *
 * Una venta no se borra: se anula. Anularla revierte el stock y registra el
 * egreso en caja, dejando ambos rastros.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();

            // Todos opcionales: se admite la venta de mostrador, sin cita ni
            // cliente registrado.
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->foreignId('personal_id')->nullable()->constrained('personal')->nullOnDelete();
            $table->foreignId('cash_session_id')->nullable()->constrained('cash_sessions')->nullOnDelete();

            $table->string('number', 20);               // correlativo por empresa
            $table->enum('status', ['pagada', 'anulada'])->default('pagada');

            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('tip', 12, 2)->default(0);   // propina del barbero
            $table->decimal('total', 12, 2)->default(0);

            $table->dateTime('sold_at');
            $table->text('notes')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'sold_at']);
            $table->index(['personal_id', 'sold_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
