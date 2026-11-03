<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Foto de cada servicio: se ve en la lista de servicios y en el catálogo
     * público. Es la ruta en el disco «public» (services/{company_id}/…).
     */
    public function up(): void
    {
        if (! Schema::hasColumn('services', 'photo')) {
            Schema::table('services', function (Blueprint $table) {
                $table->string('photo')->nullable()->after('description');
            });
        }
    }

    public function down(): void
    {
        Schema::table('services', fn (Blueprint $table) => $table->dropColumn('photo'));
    }
};
