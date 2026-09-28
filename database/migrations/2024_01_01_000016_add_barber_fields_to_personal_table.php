<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campos de la ficha de barbero.
 *
 * `bookable` distingue a quien atiende clientes de quien no (un cajero o un
 * administrativo también son personal, pero no ocupan hueco en la agenda).
 * Arranca en false: es más seguro que aparezca de más en la lista y se marque
 * a mano, que llenar el calendario de gente que no corta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personal', function (Blueprint $table) {
            $table->string('photo')->nullable()->after('full_name');
            $table->string('specialty')->nullable()->after('photo');
            $table->char('agenda_color', 7)->default('#2563eb')->after('specialty');
            $table->boolean('bookable')->default(false)->after('agenda_color');
        });
    }

    public function down(): void
    {
        Schema::table('personal', function (Blueprint $table) {
            $table->dropColumn(['photo', 'specialty', 'agenda_color', 'bookable']);
        });
    }
};
