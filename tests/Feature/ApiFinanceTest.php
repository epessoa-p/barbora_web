<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Caja;
use App\Models\CashSession;
use App\Models\Company;
use App\Models\ExpenseCategory;
use App\Models\FinanceAccount;
use App\Models\Product;
use App\Models\Stock;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Finanzas y Compras desde el móvil: mismas reglas que la web.
 */
class ApiFinanceTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = $this->companyWithPlan();
        $this->admin = $this->userInCompany($this->company, [
            'accounts.view', 'accounts.create', 'accounts.edit',
            'account_movements.create', 'account_movements.delete',
            'expenses.view', 'expenses.create', 'expenses.delete',
            'expense_categories.view', 'expense_categories.create', 'expense_categories.edit',
            'suppliers.view', 'suppliers.create', 'suppliers.edit',
            'purchases.view', 'purchases.create', 'purchases.edit', 'purchases.delete',
        ], 'admin_api');

        Sanctum::actingAs($this->admin);
    }

    protected function headers(): array
    {
        return ['X-Company-Id' => (string) $this->company->id];
    }

    public function test_cuentas_movimientos_y_transferencias(): void
    {
        $chica = $this->postJson('/api/v1/finance/accounts', ['name' => 'CAJA CHICA', 'type' => 'caja_chica', 'opening_balance' => 500], $this->headers())
            ->assertCreated()->assertJsonPath('data.balance', 500.0)->json('data.id');
        $banco = $this->postJson('/api/v1/finance/accounts', ['name' => 'BANCO', 'type' => 'banco'], $this->headers())
            ->assertCreated()->json('data.id');

        $this->postJson("/api/v1/finance/accounts/{$chica}/movements", ['type' => 'egreso', 'amount' => 100, 'concept' => 'Retiro'], $this->headers())
            ->assertCreated()->assertJsonPath('data.balance', 400.0);

        $this->postJson("/api/v1/finance/accounts/{$chica}/transfer", ['to_account_id' => $banco, 'amount' => 150], $this->headers())
            ->assertCreated()->assertJsonPath('data.balance', 250.0);

        $this->getJson('/api/v1/finance/accounts', $this->headers())->assertOk()->assertJsonPath('total', 400.0);

        $movements = $this->getJson("/api/v1/finance/accounts/{$chica}/movements", $this->headers())->assertOk();
        $this->assertSame('BANCO', $movements->json('other_accounts.0.name'));

        $retiro = collect($movements->json('data'))->firstWhere('concept', 'Retiro');
        $this->assertTrue($retiro['can_cancel']);
        $this->patchJson("/api/v1/finance/movements/{$retiro['id']}/cancel", [], $this->headers())->assertOk();
        $this->assertSame('350.00', FinanceAccount::withoutGlobalScopes()->find($chica)->balance);
    }

    public function test_gastos_desde_cuenta_o_caja_y_anular(): void
    {
        $account = FinanceAccount::withoutGlobalScopes()->create(['company_id' => $this->company->id, 'name' => 'BANCO', 'type' => 'banco', 'balance' => 1000, 'active' => true]);
        $caja = Caja::create(['company_id' => $this->company->id, 'name' => 'CAJA 1', 'balance' => 0, 'active' => true]);
        $session = CashSession::create(['company_id' => $this->company->id, 'caja_id' => $caja->id, 'status' => 'abierta', 'opened_at' => now(), 'opening_amount' => 200]);

        $options = $this->getJson('/api/v1/finance/options', $this->headers())->assertOk();
        $this->assertSame(['account', 'cash'], $options->json('sources'));
        $this->assertSame($session->id, $options->json('preferred_session_id'));
        $luz = collect($options->json('categories'))->firstWhere('name', 'LUZ')['id'];

        $id = $this->postJson('/api/v1/finance/expenses', [
            'expense_category_id' => $luz, 'description' => 'Luz', 'amount' => 120, 'spent_on' => now()->toDateString(),
            'source' => 'account', 'finance_account_id' => $account->id,
        ], $this->headers())->assertCreated()->assertJsonPath('data.can_cancel', true)->json('data.id');

        $this->assertSame('880.00', $account->refresh()->balance);

        $this->postJson('/api/v1/finance/expenses', [
            'expense_category_id' => $luz, 'description' => 'Foco', 'amount' => 30, 'spent_on' => now()->toDateString(),
            'source' => 'cash', 'cash_session_id' => $session->id,
        ], $this->headers())->assertCreated()->assertJsonPath('data.source', 'cash');

        $this->assertSame(170.0, $session->refresh()->totals()['expected_cash']);

        $this->getJson('/api/v1/finance/expenses', $this->headers())->assertOk()->assertJsonPath('total', 150.0);

        $this->patchJson("/api/v1/finance/expenses/{$id}/cancel", ['cancel_reason' => 'Error'], $this->headers())
            ->assertOk()->assertJsonPath('data.cancelled', true);
        $this->assertSame('1000.00', $account->refresh()->balance);

        // Sin origen del dinero no se registra.
        $this->postJson('/api/v1/finance/expenses', [
            'expense_category_id' => $luz, 'description' => 'X', 'amount' => 10, 'spent_on' => now()->toDateString(),
        ], $this->headers())->assertStatus(422)->assertJsonValidationErrors('source');
    }

    public function test_categorias_de_gasto(): void
    {
        $this->getJson('/api/v1/finance/expense-categories', $this->headers())->assertOk()->assertJsonPath('payees.personal', 'Alguien del personal');

        $id = $this->postJson('/api/v1/finance/expense-categories', ['name' => 'GAS', 'payee' => 'ninguno'], $this->headers())
            ->assertCreated()->json('data.id');
        $this->putJson("/api/v1/finance/expense-categories/{$id}", ['name' => 'GAS LICUADO', 'payee' => 'proveedor', 'active' => false], $this->headers())
            ->assertOk()->assertJsonPath('data.active', false);

        $this->assertSame('proveedor', ExpenseCategory::withoutGlobalScopes()->find($id)->payee);
    }

    public function test_compras_a_credito_pagos_y_anulacion(): void
    {
        $branch = Branch::factory()->create(['company_id' => $this->company->id]);
        $warehouse = Warehouse::withoutGlobalScopes()->where('branch_id', $branch->id)->firstOrFail();
        $product = Product::withoutGlobalScopes()->create([
            'company_id' => $this->company->id, 'name' => 'CERA', 'sale_price' => 40, 'cost_price' => 15,
            'is_sellable' => true, 'track_stock' => true, 'active' => true,
        ]);
        $account = FinanceAccount::withoutGlobalScopes()->create(['company_id' => $this->company->id, 'name' => 'BANCO', 'type' => 'banco', 'balance' => 1000, 'active' => true]);

        $supplier = $this->postJson('/api/v1/suppliers', ['name' => 'DISTRIBUIDORA'], $this->headers())->assertCreated()->json('data.id');

        $options = $this->getJson('/api/v1/purchases/options', $this->headers())->assertOk();
        $this->assertSame($warehouse->id, $options->json('default_warehouse_id'));
        $this->assertSame(15.0, collect($options->json('products'))->firstWhere('name', 'CERA')['cost']);

        $id = $this->postJson('/api/v1/purchases', [
            'supplier_id' => $supplier, 'warehouse_id' => $warehouse->id, 'purchased_on' => now()->toDateString(),
            'items' => [['product_id' => $product->id, 'quantity' => 5, 'unit_cost' => 20]],
            'pay_amount' => 0,
        ], $this->headers())->assertCreated()->assertJsonPath('data.status', 'pendiente')->assertJsonPath('data.balance', 100.0)->json('data.id');

        $this->assertSame(5.0, (float) Stock::withoutGlobalScopes()->where('product_id', $product->id)->value('quantity'));
        $this->getJson('/api/v1/suppliers', $this->headers())->assertOk()->assertJsonPath('data.0.debt', 100.0);
        $this->getJson('/api/v1/purchases?status=deuda', $this->headers())->assertOk()->assertJsonPath('summary.debt', 100.0);

        $this->postJson("/api/v1/purchases/{$id}/payments", [
            'amount' => 100, 'paid_on' => now()->toDateString(), 'source' => 'account', 'finance_account_id' => $account->id,
        ], $this->headers())->assertCreated()->assertJsonPath('data.status', 'pagada');

        $this->assertSame('900.00', $account->refresh()->balance);

        $this->patchJson("/api/v1/purchases/{$id}/cancel", [], $this->headers())
            ->assertOk()->assertJsonPath('data.status', 'anulada');
        $this->assertSame('1000.00', $account->refresh()->balance);
        $this->assertSame(0.0, (float) Stock::withoutGlobalScopes()->where('product_id', $product->id)->value('quantity'));
    }

    public function test_sin_permiso_ni_plan_no_entra(): void
    {
        $user = $this->userInCompany($this->company, ['appointments.view'], 'solo_mira');
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/finance/accounts', $this->headers())->assertForbidden();
        $this->getJson('/api/v1/purchases', $this->headers())->assertForbidden();

        $other = $this->companyWithPlan(planAttributes: ['features' => ['agenda']]);
        $boss = $this->userInCompany($other, ['accounts.view', 'purchases.view'], 'jefe');
        Sanctum::actingAs($boss);

        $this->getJson('/api/v1/finance/accounts', ['X-Company-Id' => (string) $other->id])
            ->assertForbidden()->assertJsonPath('error', 'plan_required');
    }
}
