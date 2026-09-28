<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reglas de comisión.
 *
 * Una regla dice A QUIÉN se aplica (`personal_id`, nulo = a todos) y SOBRE QUÉ
 * (`applies_to`: todos los servicios, todos los productos, o un servicio
 * concreto). Gana siempre la más específica, en este orden:
 *
 *   1. barbero + servicio concreto
 *   2. barbero + tipo (servicios o productos)
 *   3. servicio concreto, cualquier barbero
 *   4. tipo, cualquier barbero
 *
 * Si ninguna encaja, esa línea no genera comisión. Se prefiere eso a inventar
 * un porcentaje por defecto: una comisión que aparece sola es una discusión
 * asegurada a fin de mes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('personal_id')->nullable()->constrained('personal')->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained('services')->cascadeOnDelete();

            $table->enum('applies_to', ['servicios', 'productos', 'servicio']);
            $table->enum('type', ['porcentaje', 'monto_fijo']);
            $table->decimal('value', 10, 2);

            $table->boolean('active')->default(true);
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'personal_id', 'applies_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_rules');
    }
};
