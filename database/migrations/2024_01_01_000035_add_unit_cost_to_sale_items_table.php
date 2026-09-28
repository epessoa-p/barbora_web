<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            // El costo del producto EL DÍA DE LA VENTA.
            //
            // Hasta ahora la línea congelaba el precio de venta pero no el de
            // compra, así que el margen de los reportes se calculaba con el
            // costo actual del producto: si mañana sube el precio de compra,
            // las ganancias del mes pasado bajaban solas. Ahora se guarda, y el
            // margen histórico deja de moverse.
            //
            // NULL significa «no se sabe», y pasa en dos casos legítimos:
            //   · los servicios, que no tienen precio de compra;
            //   · las ventas anteriores a esta migración.
            // Por eso NO se rellena hacia atrás: inventar un costo sería peor
            // que admitir que esas líneas son una estimación.
            $table->decimal('unit_cost', 10, 2)->nullable()->after('unit_price');
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn('unit_cost');
        });
    }
};
