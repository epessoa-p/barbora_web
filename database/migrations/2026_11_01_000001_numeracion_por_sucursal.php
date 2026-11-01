<?php

use App\Support\BranchNumbering;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Códigos correlativos por sucursal y relaciones con la sucursal.
 *
 * - document_sequences: el último número usado por empresa, sucursal y tipo
 *   (V venta, C cita, T turno de caja, I movimiento de inventario). Se bloquea
 *   al numerar, así dos cobros a la vez no se llevan el mismo número.
 * - personal.branch_id: la sucursal base de cada persona.
 * - Número en citas, turnos y movimientos; sucursal en turnos y movimientos
 *   (la de su caja / su almacén), para numerar y filtrar sin unir tablas.
 * - Ventas: el número deja de ser único por empresa y pasa a serlo por sucursal.
 *
 * Migración ADITIVA salvo el cambio de índice único de ventas. El SQL
 * equivalente para producción está en
 * database/sql/2026_11_01_numeracion_por_sucursal.sql.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            // 0 = sin sucursal (p. ej. un almacén central). Sin FK a propósito:
            // así la clave única funciona también para ese caso.
            $table->unsignedBigInteger('branch_id')->default(0);
            $table->string('type', 5);
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();

            $table->unique(['company_id', 'branch_id', 'type']);
        });

        Schema::table('personal', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('company_id')
                ->constrained('branches')->nullOnDelete();
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->string('number', 20)->nullable()->after('branch_id');
            $table->index(['company_id', 'number']);
        });

        Schema::table('cash_sessions', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('caja_id')
                ->constrained('branches')->nullOnDelete();
            $table->string('number', 20)->nullable()->after('branch_id');
            $table->index(['company_id', 'number']);
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('warehouse_id')
                ->constrained('branches')->nullOnDelete();
            $table->string('number', 20)->nullable()->after('branch_id');
            $table->index(['company_id', 'number']);
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'number']);
        });

        // Lo ya guardado: sucursal a quien no la tiene y números por sucursal.
        BranchNumbering::backfill();

        Schema::table('sales', function (Blueprint $table) {
            $table->unique(['company_id', 'branch_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'branch_id', 'number']);
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'number']);
            $table->dropConstrainedForeignId('branch_id');
            $table->dropColumn('number');
        });

        Schema::table('cash_sessions', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'number']);
            $table->dropConstrainedForeignId('branch_id');
            $table->dropColumn('number');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'number']);
            $table->dropColumn('number');
        });

        Schema::table('personal', function (Blueprint $table) {
            $table->dropConstrainedForeignId('branch_id');
        });

        Schema::dropIfExists('document_sequences');
    }
};
