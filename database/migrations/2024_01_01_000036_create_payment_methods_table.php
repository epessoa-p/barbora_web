<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cómo cobra cada barbería.
        //
        // Antes eran cuatro valores fijos en un enum (efectivo, tarjeta,
        // transferencia, qr). En Bolivia eso deja fuera Tigo Money, el QR de un
        // banco concreto o cualquier billetera nueva, y sin poder registrarlos
        // el arqueo de caja sale mal.
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();

            $table->string('name', 60);

            // Lo que se guarda en sale_payments y cash_movements. Es estable:
            // renombrar el método no toca el historial.
            $table->string('slug', 40);

            // LA COLUMNA IMPORTANTE. Solo lo que marca esto cuenta en el arqueo,
            // porque es lo único que de verdad está en el cajón. Un pago con
            // tarjeta o por Tigo Money entra a la cuenta del banco, no al cajón:
            // sumarlo haría que el turno cuadre mal todos los días.
            $table->boolean('counts_as_cash')->default(false);

            // Para pedir el número de operación al cobrar por transferencia o QR.
            $table->boolean('requires_reference')->default(false);

            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->softDeletes();
            $table->timestamps();

            // Único por empresa: dos barberías pueden tener su propio «qr».
            $table->unique(['company_id', 'slug']);
            $table->index(['company_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
