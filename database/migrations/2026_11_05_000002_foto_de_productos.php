<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Foto de cada producto: se ve en la lista, en la ficha y en el catálogo
     * público. Es la ruta en el disco «public» (products/{company_id}/…).
     */
    public function up(): void
    {
        if (! Schema::hasColumn('products', 'photo')) {
            Schema::table('products', function (Blueprint $table) {
                $table->string('photo')->nullable()->after('description');
            });
        }
    }

    public function down(): void
    {
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('photo'));
    }
};
