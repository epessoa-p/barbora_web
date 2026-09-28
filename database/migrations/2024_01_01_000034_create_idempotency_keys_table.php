<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Evita que un reintento cobre dos veces.
        //
        // Un móvil pierde cobertura: la app manda el cobro, el servidor lo
        // registra, la respuesta se pierde y la app reintenta. Sin esto se
        // crearían DOS ventas — doble descuento de stock, doble comisión y
        // doble ingreso en caja.
        //
        // La app manda una clave por intento de cobro (cabecera
        // «Idempotency-Key»). Si la clave ya se usó, se devuelve la respuesta
        // de la primera vez en lugar de volver a cobrar.
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('key', 64);

            // Qué operación era: así una misma clave no vale para dos endpoints
            // distintos por accidente.
            $table->string('endpoint', 100);

            // Huella de lo que se envió. Si llega la misma clave con otro
            // cuerpo, es un error del cliente y hay que gritarlo, no devolver
            // en silencio la respuesta de otra venta.
            $table->string('request_hash', 64);

            $table->unsignedSmallInteger('response_status')->default(200);
            $table->json('response_body')->nullable();

            $table->timestamps();

            // Única por empresa: dos barberías pueden generar la misma clave
            // sin pisarse.
            $table->unique(['company_id', 'key']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
