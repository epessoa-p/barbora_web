<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Clientes de la barbería.
 *
 * `allergies` va en su propio campo y no dentro de las notas porque es
 * información de seguridad al aplicar tintes o químicos: tiene que saltar a la
 * vista, no quedar enterrada en un texto libre.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('full_name');
            $table->string('document_number', 30)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('preferences')->nullable();   // corte habitual, máquina, largo…
            $table->string('allergies')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('active')->default(true);
            $table->softDeletes();
            $table->timestamps();

            // Buscar por teléfono es lo primero que hace recepción al atender.
            $table->index(['company_id', 'phone']);
            $table->index(['company_id', 'full_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
