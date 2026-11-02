<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Arqueo por billetes y monedas.
     *
     * companies.cash_count_mode: cómo cierra caja la barbería, «total» (se
     * escribe lo contado, como siempre) o «denominations» (se cuentan billetes
     * y monedas). cash_sessions.closing_denominations guarda ese conteo.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('companies', 'cash_count_mode')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->string('cash_count_mode', 20)->default('total')->after('receipt_footer');
            });
        }

        if (! Schema::hasColumn('cash_sessions', 'closing_denominations')) {
            Schema::table('cash_sessions', function (Blueprint $table) {
                $table->json('closing_denominations')->nullable()->after('closing_amount');
            });
        }
    }

    public function down(): void
    {
        Schema::table('cash_sessions', fn (Blueprint $table) => $table->dropColumn('closing_denominations'));
        Schema::table('companies', fn (Blueprint $table) => $table->dropColumn('cash_count_mode'));
    }
};
