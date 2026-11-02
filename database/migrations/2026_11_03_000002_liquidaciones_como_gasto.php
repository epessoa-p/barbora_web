<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Las liquidaciones de comisiones se registran como gasto (categoría
     * «COMISIONES»): así se ven en Gastos y salen de una cuenta o de la caja,
     * como cualquier pago. El gasto queda enlazado a su liquidación.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('expenses', 'commission_settlement_id')) {
            Schema::table('expenses', function (Blueprint $table) {
                $table->foreignId('commission_settlement_id')->nullable()->after('personal_id')
                    ->constrained()->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('commission_settlement_id');
        });
    }
};
