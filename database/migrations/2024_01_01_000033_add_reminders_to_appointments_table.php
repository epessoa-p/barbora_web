<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            // Recordatorio de la cita. Hoy el aviso lo manda una persona desde
            // la pantalla de recordatorios (WhatsApp o teléfono) y aquí queda
            // el rastro de quién avisó y cuándo, para no avisar dos veces.
            //
            // Cuando exista pasarela de mensajería, el envío automático escribe
            // estas mismas columnas: el modelo de datos no cambia.
            $table->dateTime('reminded_at')->nullable()->after('cancel_reason');
            $table->foreignId('reminded_by')->nullable()->after('reminded_at')
                  ->constrained('users')->nullOnDelete();

            $table->index(['company_id', 'reminded_at']);
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropForeign(['reminded_by']);
            $table->dropIndex(['company_id', 'reminded_at']);
            $table->dropColumn(['reminded_at', 'reminded_by']);
        });
    }
};
