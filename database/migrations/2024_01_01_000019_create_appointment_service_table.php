<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Servicios de cada cita: una cita puede ser «corte + barba».
 *
 * El precio y la duración se copian aquí al reservar. Si mañana sube la tarifa,
 * la cita ya reservada mantiene lo que se pactó, y la duración con la que se
 * bloqueó el hueco sigue siendo la que ocupa en el calendario.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_service', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained();
            $table->unsignedSmallInteger('duration_minutes');
            $table->decimal('price', 10, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_service');
    }
};
