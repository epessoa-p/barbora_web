<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Suscripción de una empresa a un plan. Relación 1:1 con la empresa.
 *
 * Tabla GLOBAL: NO lleva el scope de tenant. Si se filtrara por company_id,
 * la empresa se auto-filtraría antes de poder comprobar su propio acceso.
 * Ver ARQUITECTURA §2 y §3.3.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained();

            $table->enum('status', ['trial', 'active', 'past_due', 'suspended', 'cancelled'])
                  ->default('trial');
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_end')->nullable();

            // Días de gracia tras vencer: el acceso pasa a solo lectura.
            $table->unsignedSmallInteger('grace_days')->default(3);

            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            // Overrides por empresa. NULL = heredar del plan. Ver ARQUITECTURA §8.
            $table->unsignedInteger('max_users_override')->nullable();
            $table->unsignedInteger('max_branches_override')->nullable();
            $table->unsignedInteger('max_products_override')->nullable();
            $table->json('features_override')->nullable();

            $table->timestamps();
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
