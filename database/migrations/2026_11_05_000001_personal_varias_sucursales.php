<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sucursales adicionales de una ficha de personal (un gerente que lleva
     * dos locales, por ejemplo). La sucursal BASE sigue en personal.branch_id:
     * es donde se numeran sus citas y ventas. Estas solo amplían en qué
     * sucursales puede trabajar (qué ve en los selectores).
     */
    public function up(): void
    {
        if (! Schema::hasTable('branch_personal')) {
            Schema::create('branch_personal', function (Blueprint $table) {
                $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
                $table->foreignId('personal_id')->constrained('personal')->cascadeOnDelete();
                $table->primary(['branch_id', 'personal_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_personal');
    }
};
