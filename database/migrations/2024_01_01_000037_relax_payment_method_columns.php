<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Saca `payment_method` del enum y lo deja como texto.
     *
     * Se guarda el SLUG del método, no su id: así el historial sobrevive a que
     * la barbería renombre o dé de baja un método de pago. Una venta de hace un
     * año sigue diciendo con qué se cobró aunque ese método ya no se use.
     *
     * Los valores que ya existían (efectivo, tarjeta, transferencia, qr) siguen
     * siendo válidos tal cual: no hace falta convertir nada.
     */
    public function up(): void
    {
        Schema::table('sale_payments', function (Blueprint $table) {
            $table->string('payment_method', 40)->default('efectivo')->change();
        });

        Schema::table('cash_movements', function (Blueprint $table) {
            $table->string('payment_method', 40)->default('efectivo')->change();
        });
    }

    public function down(): void
    {
        $methods = ['efectivo', 'tarjeta', 'transferencia', 'qr'];

        Schema::table('sale_payments', function (Blueprint $table) use ($methods) {
            $table->enum('payment_method', $methods)->default('efectivo')->change();
        });

        Schema::table('cash_movements', function (Blueprint $table) use ($methods) {
            $table->enum('payment_method', $methods)->default('efectivo')->change();
        });
    }
};
