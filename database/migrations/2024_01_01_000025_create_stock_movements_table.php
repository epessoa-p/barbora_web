<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Entradas, salidas y ajustes de inventario.
 *
 * Los movimientos son inmutables: no se editan ni se borran. Un error se
 * corrige con un ajuste, que es como funciona un inventario real y lo que
 * permite explicar después por qué el stock es el que es.
 *
 * `quantity_after` guarda el saldo que quedó tras el movimiento, igual que el
 * arqueo de caja: es una foto, no algo que se recalcule.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();

            $table->enum('type', ['entrada', 'salida', 'ajuste']);
            $table->decimal('quantity', 12, 2);          // con signo: + entra, − sale
            $table->decimal('quantity_after', 12, 2);

            $table->string('reason')->nullable();        // compra, consumo, merma…
            $table->decimal('unit_cost', 10, 2)->nullable();
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'created_at']);
            $table->index(['warehouse_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
