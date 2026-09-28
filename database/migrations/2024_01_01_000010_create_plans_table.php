<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Planes comerciales del operador de la plataforma.
 *
 * Tabla GLOBAL: no pertenece a ninguna empresa. Ver ARQUITECTURA §2 y §3.3.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('description')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->enum('billing_period', ['monthly', 'yearly'])->default('monthly');
            $table->unsignedSmallInteger('trial_days')->default(0);

            // Límites del plan. NULL = ilimitado.
            $table->unsignedInteger('max_users')->nullable();
            $table->unsignedInteger('max_branches')->nullable();
            $table->unsignedInteger('max_products')->nullable();

            // Módulos incluidos: ["agenda","pos",…]. Ver Plan::MODULES.
            $table->json('features')->nullable();

            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
