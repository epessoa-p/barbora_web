<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Categorías de gasto «servicio básico / recurrente»: alquiler, luz, agua,
     * internet… lo que se paga cada mes o cada año. La app las agrupa en su
     * propia pestaña de Gastos.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('expense_categories', 'recurring')) {
            Schema::table('expense_categories', function (Blueprint $table) {
                $table->boolean('recurring')->default(false)->after('payee');
            });
        }

        // Las habituales que ya se crearon a cada barbería.
        DB::table('expense_categories')
            ->whereIn('name', ['ALQUILER', 'LUZ', 'AGUA', 'INTERNET Y TELÉFONO'])
            ->update(['recurring' => true]);
    }

    public function down(): void
    {
        Schema::table('expense_categories', fn (Blueprint $table) => $table->dropColumn('recurring'));
    }
};
