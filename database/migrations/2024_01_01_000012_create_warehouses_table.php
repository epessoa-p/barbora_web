<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Almacenes de una empresa.
 *
 * Si `branch_id` no es NULL, el almacén lo creó y lo mantiene su sucursal:
 * no se edita ni se borra desde el CRUD de almacenes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 30);
            $table->string('phone', 30)->nullable();
            $table->string('address')->nullable();
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->softDeletes();
            $table->timestamps();

            // El código es único dentro de cada empresa, no globalmente.
            $table->unique(['company_id', 'code']);
            $table->index('branch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouses');
    }
};
