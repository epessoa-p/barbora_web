<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Una caja puede asignarse a un personal (el barbero que la usa).
 *
 * NULL = caja general/principal, compartida (como hasta ahora).
 *
 * Migración ADITIVA: producción ya está en uso, así que esto no se mete en la
 * migración original de cajas. El SQL equivalente para el servidor está en
 * database/sql/2026_10_03_add_personal_id_to_cajas.sql.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cajas', function (Blueprint $table) {
            $table->foreignId('personal_id')
                ->nullable()
                ->after('branch_id')
                ->constrained('personal')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cajas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('personal_id');
        });
    }
};
