<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Líneas de la comanda: servicios y productos mezclados.
 *
 * `description` y `unit_price` son copias del momento de la venta. Un ticket
 * tiene que poder reimprimirse dentro de un año diciendo lo mismo, aunque el
 * producto se haya renombrado o el precio haya subido.
 *
 * `personal_id` por línea y no solo por venta: habilita las comisiones sin
 * migrar nada, y cubre el caso real de un corte de un barbero más un producto
 * que despachó recepción.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();

            $table->enum('type', ['servicio', 'producto']);
            $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('personal_id')->nullable()->constrained('personal')->nullOnDelete();

            $table->string('description');
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('total', 12, 2);

            $table->timestamps();

            $table->index(['sale_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_items');
    }
};
