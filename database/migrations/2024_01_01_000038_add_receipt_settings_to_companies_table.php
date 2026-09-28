<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            // Texto al pie del comprobante: «Síguenos en @barberia», el horario,
            // una promoción. Si está vacío se usa «¡Gracias por tu visita!».
            $table->string('receipt_footer', 255)->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('receipt_footer');
        });
    }
};
