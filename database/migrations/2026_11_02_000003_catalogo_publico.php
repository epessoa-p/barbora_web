<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catálogo público de la barbería: una página sin sesión con sus servicios
     * (y, si quiere, sus productos), para compartir el enlace o el QR.
     *
     * Nace apagado: publicar es decisión de la barbería. El enlace lleva un
     * token difícil de adivinar, que se puede regenerar para invalidar el
     * anterior.
     */
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (! Schema::hasColumn('companies', 'catalog_enabled')) {
                $table->boolean('catalog_enabled')->default(false)->after('cash_count_mode');
            }
            if (! Schema::hasColumn('companies', 'catalog_token')) {
                $table->string('catalog_token', 32)->nullable()->unique()->after('catalog_enabled');
            }
            if (! Schema::hasColumn('companies', 'catalog_show_products')) {
                $table->boolean('catalog_show_products')->default(false)->after('catalog_token');
            }
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropUnique(['catalog_token']);
            $table->dropColumn(['catalog_enabled', 'catalog_token', 'catalog_show_products']);
        });
    }
};
