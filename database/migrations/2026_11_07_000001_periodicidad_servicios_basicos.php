<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cada cuánto se paga un servicio básico: mensual (luz, agua, internet,
     * alquiler) o anual (el hosting, una licencia…). Solo cuenta si la
     * categoría es `recurring`. El control del mes lo usa para saber si está
     * pagado: mensual, en el mes; anual, en los últimos 12 meses.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('expense_categories', 'frequency')) {
            Schema::table('expense_categories', function (Blueprint $table) {
                $table->string('frequency', 10)->default('mensual')->after('recurring');
            });
        }
    }

    public function down(): void
    {
        Schema::table('expense_categories', fn (Blueprint $table) => $table->dropColumn('frequency'));
    }
};
