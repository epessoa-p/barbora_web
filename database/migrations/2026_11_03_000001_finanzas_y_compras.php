<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Finanzas (cuentas, sus movimientos y los gastos) y Compras a proveedores.
     *
     *   finance_accounts     capital, caja chica, banco…: dinero fuera del cajón
     *   finance_movements    ingresos, egresos y transferencias de una cuenta
     *   expense_categories   alquiler, luz, comida, pasajes… (los define la barbería)
     *   suppliers            proveedores (de gastos y de compras)
     *   expenses             gastos: salen de una cuenta o de la caja abierta
     *   purchases            compras de mercadería: entran al almacén
     *   purchase_items       lo comprado
     *   purchase_payments    pagos de la compra (contado o, después, la deuda)
     *
     * Cada pago guarda de dónde salió el dinero: un movimiento de cuenta
     * (finance_movement_id) o un egreso de caja (cash_movement_id). Anular
     * revierte eso mismo; nunca se borra nada.
     */
    public function up(): void
    {
        Schema::create('finance_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            // Opcional: la caja chica de una sucursal, por ejemplo.
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('type', 20)->default('otro');          // capital, caja_chica, banco, billetera, otro
            $table->string('bank_name')->nullable();
            $table->string('account_number', 60)->nullable();
            $table->decimal('opening_balance', 15, 2)->default(0);
            // Saldo corriente: se mueve con cada movimiento, en la misma transacción.
            $table->decimal('balance', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->boolean('active')->default(true);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['company_id', 'active']);
        });

        Schema::create('finance_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('finance_account_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10);                            // ingreso, egreso
            // ingreso/egreso manual, transferencia, gasto, compra, anulacion
            $table->string('origin', 20)->default('manual');
            $table->string('concept');
            $table->decimal('amount', 15, 2);
            $table->decimal('balance_after', 15, 2);
            $table->date('occurred_on');
            $table->string('reference')->nullable();
            // La otra pata de una transferencia.
            $table->foreignId('transfer_movement_id')->nullable()->constrained('finance_movements')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['finance_account_id', 'occurred_on']);
        });

        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            // A quién se le suele pagar: proveedor, personal, o nadie en concreto.
            $table->string('payee', 20)->default('ninguno');       // ninguno, proveedor, personal
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['company_id', 'name']);
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('tax_id', 30)->nullable();
            $table->string('contact_name')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('active')->default(true);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['company_id', 'active']);
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 20)->nullable();              // G-000001 por sucursal
            $table->foreignId('expense_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('personal_id')->nullable()->constrained('personal')->nullOnDelete();
            $table->string('description');
            $table->decimal('amount', 15, 2);
            $table->date('spent_on');
            // De dónde salió: una cuenta o la caja abierta.
            $table->string('source', 10);                          // account, cash
            $table->foreignId('finance_account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('finance_movement_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('cash_session_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('cash_movement_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 15)->default('registrado');   // registrado, anulado
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancel_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'spent_on']);
            $table->index(['company_id', 'number']);
        });

        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 20)->nullable();              // P-000001 por sucursal
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->string('invoice_number', 60)->nullable();      // la factura del proveedor
            $table->date('purchased_on');
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->decimal('paid', 15, 2)->default(0);
            $table->string('status', 15)->default('pendiente');    // pendiente, parcial, pagada, anulada
            $table->date('due_on')->nullable();                    // vencimiento de la deuda
            $table->text('notes')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancel_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'purchased_on']);
            $table->index(['company_id', 'number']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('product_name');
            $table->decimal('quantity', 15, 2);
            $table->decimal('unit_cost', 15, 2);
            $table->decimal('total', 15, 2);
            $table->foreignId('stock_movement_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('purchase_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->date('paid_on');
            $table->string('source', 10);                          // account, cash
            $table->foreignId('finance_account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('finance_movement_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('cash_session_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('cash_movement_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });

        $this->seedPermissions();
    }

    /**
     * Los permisos nuevos, y quién los recibe: los roles que ya editan la
     * configuración de la barbería (el dueño / administrador) todos; el rol
     * del sistema «manager» (encargado), los de consultar y registrar.
     */
    protected function seedPermissions(): void
    {
        $permissions = [
            ['accounts.view', 'Ver Cuentas', 'accounts', 'Consultar las cuentas (capital, caja chica, banco) y sus movimientos'],
            ['accounts.create', 'Crear Cuentas', 'accounts', 'Dar de alta cuentas'],
            ['accounts.edit', 'Editar Cuentas', 'accounts', 'Modificar las cuentas'],
            ['accounts.delete', 'Eliminar Cuentas', 'accounts', 'Dar de baja cuentas'],
            ['account_movements.create', 'Crear Movimientos de cuenta', 'account_movements', 'Registrar ingresos, egresos y transferencias entre cuentas'],
            ['account_movements.delete', 'Eliminar Movimientos de cuenta', 'account_movements', 'Anular movimientos de cuenta'],
            ['expenses.view', 'Ver Gastos', 'expenses', 'Consultar los gastos'],
            ['expenses.create', 'Crear Gastos', 'expenses', 'Registrar gastos'],
            ['expenses.delete', 'Eliminar Gastos', 'expenses', 'Anular gastos'],
            ['expense_categories.view', 'Ver Categorías de gasto', 'expense_categories', 'Consultar las categorías de gasto'],
            ['expense_categories.create', 'Crear Categorías de gasto', 'expense_categories', 'Dar de alta categorías de gasto'],
            ['expense_categories.edit', 'Editar Categorías de gasto', 'expense_categories', 'Modificar las categorías de gasto'],
            ['expense_categories.delete', 'Eliminar Categorías de gasto', 'expense_categories', 'Dar de baja categorías de gasto'],
            ['suppliers.view', 'Ver Proveedores', 'suppliers', 'Consultar los proveedores'],
            ['suppliers.create', 'Crear Proveedores', 'suppliers', 'Dar de alta proveedores'],
            ['suppliers.edit', 'Editar Proveedores', 'suppliers', 'Modificar los proveedores'],
            ['suppliers.delete', 'Eliminar Proveedores', 'suppliers', 'Dar de baja proveedores'],
            ['purchases.view', 'Ver Compras', 'purchases', 'Consultar las compras y lo que se debe a proveedores'],
            ['purchases.create', 'Crear Compras', 'purchases', 'Registrar compras'],
            ['purchases.edit', 'Editar Compras', 'purchases', 'Registrar pagos de compras a crédito'],
            ['purchases.delete', 'Eliminar Compras', 'purchases', 'Anular compras'],
        ];

        foreach ($permissions as [$slug, $name, $module, $description]) {
            DB::table('permissions')->updateOrInsert(
                ['slug' => $slug],
                ['name' => $name, 'module' => $module, 'description' => $description, 'updated_at' => now(), 'created_at' => now()],
            );
        }

        $ids = DB::table('permissions')->whereIn('slug', array_column($permissions, 0))->pluck('id', 'slug');

        $owners = DB::table('role_permission')
            ->join('permissions', 'permissions.id', '=', 'role_permission.permission_id')
            ->where('permissions.slug', 'settings.edit')
            ->pluck('role_permission.role_id');

        $manager = DB::table('roles')->whereNull('company_id')->where('slug', 'manager')->value('id');
        $managerSlugs = [
            'accounts.view', 'account_movements.create', 'expenses.view', 'expenses.create',
            'expense_categories.view', 'suppliers.view', 'suppliers.create', 'suppliers.edit',
            'purchases.view', 'purchases.create', 'purchases.edit',
        ];

        $grant = function (int $roleId, array $slugs) use ($ids) {
            foreach ($slugs as $slug) {
                if (isset($ids[$slug])) {
                    DB::table('role_permission')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $ids[$slug]]);
                }
            }
        };

        foreach ($owners->unique() as $roleId) {
            $grant((int) $roleId, $ids->keys()->all());
        }

        if ($manager) {
            $grant((int) $manager, $managerSlugs);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_payments');
        Schema::dropIfExists('purchase_items');
        Schema::dropIfExists('purchases');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('expense_categories');
        Schema::dropIfExists('finance_movements');
        Schema::dropIfExists('finance_accounts');

        DB::table('permissions')->whereIn('module', [
            'accounts', 'account_movements', 'expenses', 'expense_categories', 'suppliers', 'purchases',
        ])->delete();
    }
};
