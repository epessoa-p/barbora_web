<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Caja;
use App\Models\CashSession;
use App\Models\Company;
use App\Models\FinanceAccount;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Stock;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Compras a proveedores: la mercadería entra al almacén; se paga al contado
 * (desde una cuenta o la caja abierta), en parte o a crédito.
 */
class PurchaseTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected User $admin;
    protected Warehouse $warehouse;
    protected Product $cera;
    protected Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = $this->companyWithPlan();
        $this->admin = $this->userInCompany($this->company, [
            'purchases.view', 'purchases.create', 'purchases.edit', 'purchases.delete',
            'suppliers.view', 'suppliers.create', 'suppliers.edit', 'suppliers.delete',
            'accounts.view',
        ], 'compras');

        $branch = Branch::factory()->create(['company_id' => $this->company->id]);
        $this->warehouse = Warehouse::withoutGlobalScopes()->where('branch_id', $branch->id)->firstOrFail();
        $this->cera = Product::withoutGlobalScopes()->create([
            'company_id' => $this->company->id, 'name' => 'CERA MATE', 'sale_price' => 45, 'cost_price' => 20,
            'is_sellable' => true, 'track_stock' => true, 'active' => true,
        ]);
        $this->supplier = Supplier::withoutGlobalScopes()->create(['company_id' => $this->company->id, 'name' => 'DISTRIBUIDORA NORTE', 'active' => true]);
    }

    protected function act(): static
    {
        return $this->actingInCompany($this->admin, $this->company);
    }

    protected function stock(): float
    {
        return (float) Stock::withoutGlobalScopes()->where('product_id', $this->cera->id)->where('warehouse_id', $this->warehouse->id)->value('quantity');
    }

    protected function payload(array $extra = []): array
    {
        return $extra + [
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'purchased_on' => now()->toDateString(),
            'items' => [['product_id' => $this->cera->id, 'quantity' => 10, 'unit_cost' => 22]],
        ];
    }

    public function test_al_contado_desde_la_caja_entra_al_almacen_y_sale_del_cajon(): void
    {
        $caja = Caja::create(['company_id' => $this->company->id, 'name' => 'CAJA 1', 'balance' => 0, 'active' => true]);
        $session = CashSession::create([
            'company_id' => $this->company->id, 'caja_id' => $caja->id,
            'status' => 'abierta', 'opened_at' => now(), 'opening_amount' => 300,
        ]);

        $this->act()->get(route('purchases.create'))->assertOk()->assertSee('CERA MATE')->assertSee('Caja abierta');

        $this->act()->post(route('purchases.store'), $this->payload([
            'pay_amount' => 220, 'source' => 'cash', 'cash_session_id' => $session->id,
        ]))->assertRedirect();

        $purchase = Purchase::withoutGlobalScopes()->firstOrFail();
        $this->assertStringStartsWith('P-', $purchase->number);
        $this->assertSame('pagada', $purchase->status);
        $this->assertSame(10.0, $this->stock());
        $this->assertSame('22.00', $this->cera->refresh()->cost_price, 'El costo pasa a ser el de la última compra.');
        $this->assertSame(80.0, $session->refresh()->totals()['expected_cash']);

        $this->act()->get(route('purchases.show', $purchase))->assertOk()->assertSee('Pagada');
        $this->act()->get(route('purchases.index'))->assertOk()->assertSee($purchase->number);
    }

    public function test_a_credito_queda_la_deuda_y_se_paga_despues_desde_una_cuenta(): void
    {
        $account = FinanceAccount::withoutGlobalScopes()->create([
            'company_id' => $this->company->id, 'name' => 'BANCO', 'type' => 'banco', 'balance' => 1000, 'active' => true,
        ]);

        // Sin pago: no hace falta decir de dónde sale el dinero.
        $this->act()->post(route('purchases.store'), $this->payload(['pay_amount' => 0, 'due_on' => now()->addDays(30)->toDateString()]))
            ->assertRedirect();

        $purchase = Purchase::withoutGlobalScopes()->firstOrFail();
        $this->assertSame('pendiente', $purchase->status);
        $this->assertSame(220.0, $purchase->balance());
        $this->assertSame(220.0, $this->supplier->debt());

        $this->act()->post(route('purchases.pay', $purchase), [
            'amount' => 100, 'paid_on' => now()->toDateString(), 'source' => 'account', 'finance_account_id' => $account->id,
        ])->assertRedirect();

        $this->assertSame('parcial', $purchase->refresh()->status);
        $this->assertSame('900.00', $account->refresh()->balance);

        // No se paga más de lo que se debe.
        $this->act()->post(route('purchases.pay', $purchase), [
            'amount' => 500, 'paid_on' => now()->toDateString(), 'source' => 'account', 'finance_account_id' => $account->id,
        ])->assertSessionHasErrors('amount');

        $this->act()->post(route('purchases.pay', $purchase), [
            'amount' => 120, 'paid_on' => now()->toDateString(), 'source' => 'account', 'finance_account_id' => $account->id,
        ])->assertRedirect();

        $this->assertSame('pagada', $purchase->refresh()->status);
        $this->assertSame(0.0, $this->supplier->debt());

        $this->act()->get(route('suppliers.show', $this->supplier))->assertOk()->assertSee($purchase->number);
        $this->act()->get(route('suppliers.index'))->assertOk()->assertSee('DISTRIBUIDORA NORTE');
    }

    public function test_anular_saca_la_mercaderia_y_devuelve_los_pagos(): void
    {
        $account = FinanceAccount::withoutGlobalScopes()->create([
            'company_id' => $this->company->id, 'name' => 'CAPITAL', 'type' => 'capital', 'balance' => 1000, 'active' => true,
        ]);

        $this->act()->post(route('purchases.store'), $this->payload([
            'pay_amount' => 100, 'source' => 'account', 'finance_account_id' => $account->id,
        ]))->assertRedirect();

        $purchase = Purchase::withoutGlobalScopes()->firstOrFail();
        $this->assertSame('parcial', $purchase->status);
        $this->assertSame('900.00', $account->refresh()->balance);

        $this->act()->patch(route('purchases.cancel', $purchase), ['cancel_reason' => 'Mercadería fallada'])->assertRedirect();

        $this->assertSame('anulada', $purchase->refresh()->status);
        $this->assertSame(0.0, $this->stock());
        $this->assertSame('1000.00', $account->refresh()->balance);
        $this->assertSame(0.0, $this->supplier->debt());
    }

    public function test_no_se_paga_mas_que_el_total_ni_sin_productos(): void
    {
        $this->act()->post(route('purchases.store'), $this->payload(['pay_amount' => 999]))
            ->assertSessionHasErrors('pay_amount');

        $this->act()->post(route('purchases.store'), ['warehouse_id' => $this->warehouse->id, 'purchased_on' => now()->toDateString(), 'items' => []])
            ->assertSessionHasErrors('items');

        $this->assertSame(0, Purchase::withoutGlobalScopes()->count());
    }

    public function test_proveedores_se_crean_y_con_compras_se_dan_de_baja(): void
    {
        $this->act()->post(route('suppliers.store'), ['name' => 'BARBER SUPPLY', 'phone' => '7000'])->assertRedirect();
        $this->act()->postJson(route('suppliers.quick-store'), ['name' => 'RÁPIDO SRL'])->assertCreated()->assertJsonPath('data.name', 'RÁPIDO SRL');

        $this->act()->post(route('purchases.store'), $this->payload(['pay_amount' => 0]))->assertRedirect();
        $this->act()->delete(route('suppliers.destroy', $this->supplier))->assertRedirect();

        $this->assertFalse($this->supplier->refresh()->active, 'Con compras no se borra: se da de baja.');
        $this->assertNull($this->supplier->deleted_at);
    }

    public function test_sin_permiso_no_se_compra(): void
    {
        $user = $this->userInCompany($this->company, ['products.view'], 'solo_mira');

        $this->actingInCompany($user, $this->company)->get(route('purchases.index'))->assertForbidden();
        $this->actingInCompany($user, $this->company)->post(route('purchases.store'), $this->payload())->assertForbidden();
    }
}
