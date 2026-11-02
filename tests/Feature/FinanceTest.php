<?php

namespace Tests\Feature;

use App\Models\Caja;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\Company;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FinanceAccount;
use App\Models\FinanceMovement;
use App\Models\Personal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Finanzas: cuentas (capital, caja chica, banco), sus movimientos y los
 * gastos, que salen de una cuenta o de la caja abierta.
 */
class FinanceTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = $this->companyWithPlan();
        $this->admin = $this->userInCompany($this->company, [
            'accounts.view', 'accounts.create', 'accounts.edit', 'accounts.delete',
            'account_movements.create', 'account_movements.delete',
            'expenses.view', 'expenses.create', 'expenses.delete',
            'expense_categories.view', 'expense_categories.create', 'expense_categories.edit', 'expense_categories.delete',
            'cash.view',
        ], 'finanzas');
    }

    protected function act(): static
    {
        return $this->actingInCompany($this->admin, $this->company);
    }

    protected function account(string $name = 'CAJA CHICA', float $opening = 500): FinanceAccount
    {
        $this->act()->post(route('accounts.store'), ['name' => $name, 'type' => 'caja_chica', 'opening_balance' => $opening])
            ->assertRedirect();

        return FinanceAccount::withoutGlobalScopes()->where('name', $name)->firstOrFail();
    }

    protected function openSession(float $opening = 100): CashSession
    {
        $caja = Caja::create(['company_id' => $this->company->id, 'name' => 'CAJA 1', 'balance' => 0, 'active' => true]);

        return CashSession::create([
            'company_id' => $this->company->id, 'caja_id' => $caja->id,
            'status' => 'abierta', 'opened_at' => now(), 'opening_amount' => $opening,
        ]);
    }

    protected function category(string $name = 'LUZ'): ExpenseCategory
    {
        ExpenseCategory::ensureDefaults($this->company->id);

        return ExpenseCategory::withoutGlobalScopes()->where('company_id', $this->company->id)->where('name', $name)->firstOrFail();
    }

    public function test_el_saldo_inicial_entra_como_movimiento(): void
    {
        $account = $this->account(opening: 500);

        $this->assertSame('500.00', $account->balance);
        $this->assertSame('apertura', FinanceMovement::withoutGlobalScopes()->where('finance_account_id', $account->id)->value('origin'));

        $this->act()->get(route('accounts.index'))->assertOk()->assertSee('CAJA CHICA');
        $this->act()->get(route('accounts.show', $account))->assertOk()->assertSee('Saldo inicial');
    }

    public function test_ingresos_egresos_y_transferencias_mueven_el_saldo(): void
    {
        $chica = $this->account('CAJA CHICA', 500);
        $banco = $this->account('BANCO', 0);

        $this->act()->post(route('accounts.movements.store', $chica), [
            'type' => 'egreso', 'amount' => 120, 'concept' => 'Retiro', 'occurred_on' => now()->toDateString(),
        ])->assertRedirect();

        $this->act()->post(route('accounts.transfer', $chica), [
            'to_account_id' => $banco->id, 'amount' => 300, 'occurred_on' => now()->toDateString(),
        ])->assertRedirect();

        $this->assertSame('80.00', $chica->refresh()->balance);
        $this->assertSame('300.00', $banco->refresh()->balance);

        // Anular la transferencia anula las dos patas.
        $out = FinanceMovement::withoutGlobalScopes()->where('finance_account_id', $chica->id)->where('origin', 'transferencia')->firstOrFail();
        $this->act()->patch(route('accounts.movements.cancel', $out))->assertRedirect();

        $this->assertSame('380.00', $chica->refresh()->balance);
        $this->assertSame('0.00', $banco->refresh()->balance);
    }

    public function test_un_gasto_desde_una_cuenta_descuenta_y_al_anular_vuelve(): void
    {
        $account = $this->account(opening: 500);
        $luz = $this->category('LUZ');

        $this->act()->get(route('expenses.create'))->assertOk()->assertSee('Caja abierta')->assertSee('LUZ');

        $this->act()->post(route('expenses.store'), [
            'expense_category_id' => $luz->id, 'description' => 'Luz de octubre', 'amount' => 180,
            'spent_on' => now()->toDateString(), 'source' => 'account', 'finance_account_id' => $account->id,
        ])->assertRedirect(route('expenses.index'));

        $expense = Expense::withoutGlobalScopes()->firstOrFail();
        $this->assertStringStartsWith('G-', $expense->number);
        $this->assertSame('320.00', $account->refresh()->balance);

        $this->act()->get(route('expenses.index'))->assertOk()->assertSee('Luz de octubre');
        $this->act()->get(route('expenses.show', $expense))->assertOk();

        $this->act()->patch(route('expenses.cancel', $expense), ['cancel_reason' => 'Duplicado'])->assertRedirect();

        $this->assertTrue($expense->refresh()->isCancelled());
        $this->assertSame('500.00', $account->refresh()->balance);
    }

    public function test_un_gasto_desde_la_caja_abierta_baja_el_esperado_del_arqueo(): void
    {
        $session = $this->openSession(100);
        $comida = $this->category('COMIDA');

        $this->act()->post(route('expenses.store'), [
            'expense_category_id' => $comida->id, 'description' => 'Almuerzo', 'amount' => 35,
            'spent_on' => now()->toDateString(), 'source' => 'cash', 'cash_session_id' => $session->id,
        ])->assertRedirect();

        $this->assertSame(65.0, $session->refresh()->totals()['expected_cash']);
        $this->assertSame('egreso', CashMovement::withoutGlobalScopes()->firstOrFail()->type);

        // Anular devuelve el dinero al turno como ingreso.
        $this->act()->patch(route('expenses.cancel', Expense::withoutGlobalScopes()->firstOrFail()))->assertRedirect();
        $this->assertSame(100.0, $session->refresh()->totals()['expected_cash']);
    }

    public function test_el_pago_a_personal_guarda_a_quien_y_no_sale_de_un_turno_cerrado(): void
    {
        $session = $this->openSession(500);
        $session->update(['status' => 'cerrada']);
        $tania = Personal::withoutGlobalScopes()->create(['company_id' => $this->company->id, 'full_name' => 'TANIA', 'active' => true]);

        $this->act()->post(route('expenses.store'), [
            'expense_category_id' => $this->category('PAGO A PERSONAL')->id, 'personal_id' => $tania->id,
            'description' => 'Adelanto', 'amount' => 50, 'spent_on' => now()->toDateString(),
            'source' => 'cash', 'cash_session_id' => $session->id,
        ])->assertSessionHasErrors('cash_session_id');

        $account = $this->account(opening: 100);
        $this->act()->post(route('expenses.store'), [
            'expense_category_id' => $this->category('PAGO A PERSONAL')->id, 'personal_id' => $tania->id,
            'description' => 'Adelanto', 'amount' => 50, 'spent_on' => now()->toDateString(),
            'source' => 'account', 'finance_account_id' => $account->id,
        ])->assertRedirect();

        $this->assertSame('TANIA', Expense::withoutGlobalScopes()->firstOrFail()->payeeName());
    }

    public function test_las_categorias_se_crean_editan_y_dan_de_baja(): void
    {
        $this->act()->get(route('expense-categories.index'))->assertOk()->assertSee('ALQUILER');

        $this->act()->post(route('expense-categories.store'), ['name' => 'PUBLICIDAD', 'payee' => 'proveedor'])->assertRedirect();
        $this->act()->post(route('expense-categories.store'), ['name' => 'PUBLICIDAD', 'payee' => 'ninguno'])
            ->assertSessionHasErrors('name');

        $cat = ExpenseCategory::withoutGlobalScopes()->where('name', 'PUBLICIDAD')->firstOrFail();
        $this->act()->put(route('expense-categories.update', $cat), ['name' => 'MARKETING', 'payee' => 'proveedor', 'active' => 1])->assertRedirect();
        $this->assertSame('MARKETING', $cat->refresh()->name);
    }

    public function test_ganancias_resta_los_gastos_por_categoria_sin_contar_compras_dos_veces(): void
    {
        $account = $this->account(opening: 1000);
        $session = $this->openSession(500);

        // Un gasto desde una cuenta y otro desde la caja.
        $this->act()->post(route('expenses.store'), [
            'expense_category_id' => $this->category('ALQUILER')->id, 'description' => 'Alquiler', 'amount' => 300,
            'spent_on' => now()->toDateString(), 'source' => 'account', 'finance_account_id' => $account->id,
        ])->assertRedirect();
        $this->act()->post(route('expenses.store'), [
            'expense_category_id' => $this->category('COMIDA')->id, 'description' => 'Almuerzo', 'amount' => 40,
            'spent_on' => now()->toDateString(), 'source' => 'cash', 'cash_session_id' => $session->id,
        ])->assertRedirect();

        // Un gasto anulado no cuenta.
        $this->act()->post(route('expenses.store'), [
            'expense_category_id' => $this->category('LUZ')->id, 'description' => 'Duplicado', 'amount' => 99,
            'spent_on' => now()->toDateString(), 'source' => 'account', 'finance_account_id' => $account->id,
        ]);
        $this->act()->patch(route('expenses.cancel', Expense::withoutGlobalScopes()->where('description', 'Duplicado')->firstOrFail()));

        // Un egreso suelto en la caja (registrado directo en el turno) sí cuenta.
        $session->movements()->create([
            'company_id' => $this->company->id, 'type' => 'egreso', 'payment_method' => 'efectivo',
            'concept' => 'Retiro', 'amount' => 25,
        ]);

        // Pagar una compra desde la caja NO es gasto: la mercadería resta como costo al venderse.
        \App\Models\PurchasePayment::withoutGlobalScopes()->create([
            'company_id' => $this->company->id, 'purchase_id' => \App\Models\Purchase::withoutGlobalScopes()->create([
                'company_id' => $this->company->id,
                'warehouse_id' => \App\Models\Warehouse::withoutGlobalScopes()->create(['company_id' => $this->company->id, 'name' => 'ALM', 'code' => 'ALM', 'active' => true])->id,
                'purchased_on' => now()->toDateString(), 'total' => 200, 'paid' => 200, 'status' => 'pagada',
            ])->id,
            'amount' => 200, 'paid_on' => now()->toDateString(), 'source' => 'cash', 'cash_session_id' => $session->id,
            'cash_movement_id' => $session->movements()->create([
                'company_id' => $this->company->id, 'type' => 'egreso', 'payment_method' => 'efectivo',
                'concept' => 'Compra', 'amount' => 200,
            ])->id,
        ]);

        $earnings = $this->inCompany($this->company, fn () => app(\App\Support\Reports\EarningsReport::class)->for(
            new \App\Support\ReportPeriod(now()->toImmutable()->startOfDay(), now()->toImmutable()->endOfDay()),
        ));

        $this->assertSame(365.0, $earnings['other_expenses'], '300 + 40 + 25; ni el anulado ni la compra.');
        $this->assertSame(25.0, $earnings['loose_cash_expenses']);
        $this->assertSame(['ALQUILER', 'COMIDA'], array_column($earnings['expenses_by_category'], 'category'));

        $reports = $this->userInCompany($this->company, ['reports.view'], 'reportes');
        $this->actingInCompany($reports, $this->company)->get(route('reports.earnings'))->assertOk()->assertSee('ALQUILER');
    }

    public function test_las_pantallas_de_alta_y_edicion_cargan(): void
    {
        $account = $this->account();
        $user = $this->userInCompany($this->company, [
            'accounts.view', 'accounts.create', 'accounts.edit', 'suppliers.view', 'suppliers.create', 'suppliers.edit',
        ], 'pantallas');
        $supplier = \App\Models\Supplier::withoutGlobalScopes()->create(['company_id' => $this->company->id, 'name' => 'PROV', 'active' => true]);

        foreach ([route('accounts.create'), route('accounts.edit', $account), route('suppliers.create'), route('suppliers.edit', $supplier)] as $url) {
            $this->actingInCompany($user, $this->company)->get($url)->assertOk();
        }

        // El menú lateral muestra las secciones nuevas.
        $this->act()->get(route('accounts.index'))->assertSee('Finanzas')->assertSee('Categorías de gasto');
    }

    public function test_sin_el_modulo_finanzas_no_hay_cuentas_ni_gastos(): void
    {
        $company = $this->companyWithPlan(planAttributes: ['features' => ['agenda', 'caja']]);
        $user = $this->userInCompany($company, ['accounts.view', 'expenses.view'], 'sin_plan');

        $this->actingInCompany($user, $company)->get(route('accounts.index'))->assertForbidden();
        $this->actingInCompany($user, $company)->get(route('expenses.index'))->assertForbidden();
    }
}
