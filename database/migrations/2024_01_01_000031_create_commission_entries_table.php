<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Comisión devengada por una línea de venta.
 *
 * Se calcula al cobrar y se guarda con la regla que se aplicó, su tipo y su
 * valor. Si mañana se cambia el porcentaje, lo ya devengado no se mueve — y al
 * barbero se le puede explicar exactamente de dónde sale cada importe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_item_id')->constrained('sale_items')->cascadeOnDelete();
            $table->foreignId('personal_id')->constrained('personal')->cascadeOnDelete();
            $table->foreignId('commission_rule_id')->nullable()->constrained('commission_rules')->nullOnDelete();
            $table->foreignId('commission_settlement_id')->nullable()
                  ->constrained('commission_settlements')->nullOnDelete();

            $table->decimal('base_amount', 12, 2);      // importe de la línea
            $table->enum('rate_type', ['porcentaje', 'monto_fijo']);
            $table->decimal('rate_value', 10, 2);
            $table->decimal('amount', 12, 2);           // comisión resultante

            $table->dateTime('earned_at');
            $table->timestamps();

            $table->index(['company_id', 'personal_id', 'earned_at']);
            $table->index('commission_settlement_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_entries');
    }
};
