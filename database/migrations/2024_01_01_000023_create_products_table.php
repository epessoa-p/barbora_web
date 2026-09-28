<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Productos: tanto los que se venden al cliente como los insumos de uso interno
 * (tintes, toallas, cuchillas).
 *
 * `track_stock` permite tener productos sin control de existencias: hay
 * consumibles que no compensa contar uno a uno, y obligar a hacerlo solo
 * consigue que el inventario deje de cuadrar y se abandone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_category_id')->nullable()
                  ->constrained('product_categories')->nullOnDelete();

            $table->string('name');
            $table->string('sku', 50)->nullable();
            $table->string('description')->nullable();
            $table->string('unit', 20)->default('unidad');

            $table->decimal('cost_price', 10, 2)->default(0);
            $table->decimal('sale_price', 10, 2)->default(0);

            $table->boolean('is_sellable')->default(true);      // se vende o es solo insumo
            $table->boolean('track_stock')->default(true);
            $table->decimal('min_stock', 12, 2)->default(0);    // umbral de aviso

            $table->boolean('active')->default(true);
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['company_id', 'name']);
            $table->unique(['company_id', 'sku']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
