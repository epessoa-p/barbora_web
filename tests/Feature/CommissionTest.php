<?php

namespace Tests\Feature;

use App\Models\Caja;
use App\Models\Cargo;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\CommissionEntry;
use App\Models\CommissionRule;
use App\Models\CommissionSettlement;
use App\Models\Company;
use App\Models\Personal;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Service;
use App\Models\Branch;
use App\Models\Warehouse;
use App\Support\StockManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Comisiones.
 *
 * Lo esencial: que gane siempre la regla más específica, que lo devengado se
 * congele con la regla del día, y que liquidar saque el dinero de la caja.
 */
class CommissionTest extends TestCase
{
    use RefreshDatabase;

    protected function company(): Company
    {
        return $this->companyWithPlan(planAttributes: [
            'features' => ['pos', 'caja', 'inventario', 'agenda', 'comisiones'],
        ]);
    }

    protected function barber(Company $company, string $name = null): Personal
    {
        $cargo = Cargo::firstOrCreate(
            ['company_id' => $company->id, 'name' => 'Barbero'],
            ['active' => true]
        );

        return Personal::create([
            'company_id' => $company->id,
            'cargo_id' => $cargo->id,
            'full_name' => $name ?? fake()->name(),
            'bookable' => true,
            'active' => true,
        ]);
    }

    protected function warehouse(Company $company): Warehouse
    {
        $branch = Branch::factory()->create(['company_id' => $company->id]);

        return Warehouse::allCompanies()->where('branch_id', $branch->id)->firstOrFail();
    }

    protected function openCash(Company $company): CashSession
    {
        $caja = Caja::create([
            'company_id' => $company->id,
            'name' => 'Caja '.fake()->unique()->numerify('###'),
            'balance' => 0, 'active' => true,
        ]);

        return CashSession::create([
            'company_id' => $company->id, 'caja_id' => $caja->id, 'status' => 'abierta',
            'opened_at' => now(), 'opening_amount' => 500,
        ]);
    }

    protected function rule(Company $company, array $attributes): CommissionRule
    {
        return CommissionRule::create(array_merge([
            'company_id' => $company->id,
            'applies_to' => 'servicios',
            'type' => 'porcentaje',
            'value' => 40,
            'active' => true,
        ], $attributes));
    }

    /** Cobra una venta y devuelve el modelo. */
    protected function sell(Company $company, Personal $barber, array $items, float $tip = 0): Sale
    {
        $user = $this->userInCompany($company, ['sales.view', 'sales.create'], 'vendedor');

        $lines = collect($items)->map(fn ($i) => [
            'type' => $i['type'], 'id' => $i['id'], 'quantity' => $i['quantity'] ?? 1,
        ])->all();

        $total = collect($items)->sum(fn ($i) => $i['price'] * ($i['quantity'] ?? 1)) + $tip;

        $this->actingInCompany($user, $company)
            ->post(route('sales.store'), [
                'personal_id' => $barber->id,
                'tip' => $tip,
                'items' => $lines,
                'payments' => [['payment_method' => 'efectivo', 'amount' => $total]],
            ])
            ->assertRedirect();

        return Sale::allCompanies()->latest('id')->firstOrFail();
    }

    // ── Devengo ─────────────────────────────────────────────────────────────

    public function test_se_devenga_comision_al_cobrar(): void
    {
        $company = $this->company();
        $this->warehouse($company);
        $this->openCash($company);
        $barber = $this->barber($company);
        $service = Service::factory()->create(['company_id' => $company->id, 'price' => 100]);
        $this->rule($company, ['value' => 40]);

        $this->sell($company, $barber, [['type' => 'servicio', 'id' => $service->id, 'price' => 100]]);

        $entry = CommissionEntry::allCompanies()->firstOrFail();

        $this->assertSame('100.00', $entry->base_amount);
        $this->assertSame('40.00', $entry->amount);
        $this->assertSame($barber->id, $entry->personal_id);
    }

    public function test_sin_reglas_no_se_devenga_nada(): void
    {
        $company = $this->company();
        $this->warehouse($company);
        $this->openCash($company);
        $barber = $this->barber($company);
        $service = Service::factory()->create(['company_id' => $company->id, 'price' => 100]);

        $this->sell($company, $barber, [['type' => 'servicio', 'id' => $service->id, 'price' => 100]]);

        $this->assertSame(0, CommissionEntry::allCompanies()->count());
    }

    public function test_sin_barbero_asignado_no_se_devenga(): void
    {
        $company = $this->company();
        $this->warehouse($company);
        $this->openCash($company);
        $service = Service::factory()->create(['company_id' => $company->id, 'price' => 100]);
        $this->rule($company, ['value' => 40]);

        $user = $this->userInCompany($company, ['sales.view', 'sales.create']);

        // Venta de mostrador: nadie a quien comisionar.
        $this->actingInCompany($user, $company)
            ->post(route('sales.store'), [
                'items' => [['type' => 'servicio', 'id' => $service->id, 'quantity' => 1]],
                'payments' => [['payment_method' => 'efectivo', 'amount' => 100]],
            ])->assertRedirect();

        $this->assertSame(0, CommissionEntry::allCompanies()->count());
    }

    public function test_un_monto_fijo_no_depende_del_importe(): void
    {
        $company = $this->company();
        $this->warehouse($company);
        $this->openCash($company);
        $barber = $this->barber($company);
        $service = Service::factory()->create(['company_id' => $company->id, 'price' => 250]);
        $this->rule($company, ['type' => 'monto_fijo', 'value' => 15]);

        $this->sell($company, $barber, [['type' => 'servicio', 'id' => $service->id, 'price' => 250]]);

        $this->assertSame('15.00', CommissionEntry::allCompanies()->first()->amount);
    }

    // ── Precedencia de reglas ───────────────────────────────────────────────

    public function test_la_regla_del_barbero_gana_a_la_general(): void
    {
        $company = $this->company();
        $this->warehouse($company);
        $this->openCash($company);
        $barber = $this->barber($company);
        $service = Service::factory()->create(['company_id' => $company->id, 'price' => 100]);

        $this->rule($company, ['value' => 40]);                                   // prioridad 4
        $this->rule($company, ['personal_id' => $barber->id, 'value' => 45]);     // prioridad 2

        $this->sell($company, $barber, [['type' => 'servicio', 'id' => $service->id, 'price' => 100]]);

        $this->assertSame('45.00', CommissionEntry::allCompanies()->first()->amount);
    }

    public function test_la_regla_de_un_servicio_gana_a_la_de_todos_los_servicios(): void
    {
        $company = $this->company();
        $this->warehouse($company);
        $this->openCash($company);
        $barber = $this->barber($company);
        $service = Service::factory()->create(['company_id' => $company->id, 'price' => 200]);

        $this->rule($company, ['value' => 40]);                                                    // prioridad 4
        $this->rule($company, ['applies_to' => 'servicio', 'service_id' => $service->id, 'value' => 30]); // prioridad 3

        $this->sell($company, $barber, [['type' => 'servicio', 'id' => $service->id, 'price' => 200]]);

        $this->assertSame('60.00', CommissionEntry::allCompanies()->first()->amount);
    }

    public function test_barbero_mas_servicio_concreto_gana_a_todo(): void
    {
        $company = $this->company();
        $this->warehouse($company);
        $this->openCash($company);
        $barber = $this->barber($company);
        $service = Service::factory()->create(['company_id' => $company->id, 'price' => 100]);

        $this->rule($company, ['value' => 40]);                                                       // 4
        $this->rule($company, ['applies_to' => 'servicio', 'service_id' => $service->id, 'value' => 30]); // 3
        $this->rule($company, ['personal_id' => $barber->id, 'value' => 45]);                         // 2
        $this->rule($company, ['personal_id' => $barber->id, 'applies_to' => 'servicio',
                               'service_id' => $service->id, 'value' => 50]);                         // 1

        $this->sell($company, $barber, [['type' => 'servicio', 'id' => $service->id, 'price' => 100]]);

        $this->assertSame('50.00', CommissionEntry::allCompanies()->first()->amount);
    }

    public function test_la_regla_de_otro_barbero_no_se_aplica(): void
    {
        $company = $this->company();
        $this->warehouse($company);
        $this->openCash($company);
        $mio = $this->barber($company, 'Mío');
        $otro = $this->barber($company, 'Otro');
        $service = Service::factory()->create(['company_id' => $company->id, 'price' => 100]);

        $this->rule($company, ['value' => 40]);
        $this->rule($company, ['personal_id' => $otro->id, 'value' => 90]);

        $this->sell($company, $mio, [['type' => 'servicio', 'id' => $service->id, 'price' => 100]]);

        $this->assertSame('40.00', CommissionEntry::allCompanies()->first()->amount);
    }

    public function test_productos_y_servicios_comisionan_distinto(): void
    {
        $company = $this->company();
        $warehouse = $this->warehouse($company);
        $this->openCash($company);
        $barber = $this->barber($company);
        $service = Service::factory()->create(['company_id' => $company->id, 'price' => 100]);

        $product = Product::create([
            'company_id' => $company->id, 'name' => 'Cera', 'unit' => 'unidad',
            'cost_price' => 20, 'sale_price' => 50, 'min_stock' => 0,
            'is_sellable' => true, 'track_stock' => true, 'active' => true,
        ]);
        app(StockManager::class)->receive($product, $warehouse, 10);

        $this->rule($company, ['applies_to' => 'servicios', 'value' => 40]);
        $this->rule($company, ['applies_to' => 'productos', 'value' => 10]);

        $this->sell($company, $barber, [
            ['type' => 'servicio', 'id' => $service->id, 'price' => 100],
            ['type' => 'producto', 'id' => $product->id, 'price' => 50],
        ]);

        // Se comprueba por tipo de línea, no por orden: lo que importa es que
        // cada una cobre su tasa, no en qué orden se grabaron.
        $entries = CommissionEntry::allCompanies()->with('item')->get();

        $this->assertSame('40.00', $entries->first(fn ($e) => $e->item->type === 'servicio')->amount);
        $this->assertSame('5.00', $entries->first(fn ($e) => $e->item->type === 'producto')->amount);
    }

    // ── Congelado histórico ─────────────────────────────────────────────────

    public function test_cambiar_la_regla_no_altera_lo_ya_devengado(): void
    {
        $company = $this->company();
        $this->warehouse($company);
        $this->openCash($company);
        $barber = $this->barber($company);
        $service = Service::factory()->create(['company_id' => $company->id, 'price' => 100]);
        $rule = $this->rule($company, ['value' => 40]);

        $this->sell($company, $barber, [['type' => 'servicio', 'id' => $service->id, 'price' => 100]]);

        $rule->update(['value' => 10]);

        $entry = CommissionEntry::allCompanies()->first();

        $this->assertSame('40.00', $entry->amount);
        $this->assertSame('40.00', $entry->rate_value);
    }

    // ── Anulación ───────────────────────────────────────────────────────────

    public function test_anular_una_venta_borra_su_comision_pendiente(): void
    {
        $company = $this->company();
        $this->warehouse($company);
        $this->openCash($company);
        $barber = $this->barber($company);
        $service = Service::factory()->create(['company_id' => $company->id, 'price' => 100]);
        $this->rule($company, ['value' => 40]);

        $sale = $this->sell($company, $barber, [['type' => 'servicio', 'id' => $service->id, 'price' => 100]]);
        $this->assertSame(1, CommissionEntry::allCompanies()->count());

        $admin = $this->userInCompany($company, ['sales.view', 'sales.delete'], 'anulador');
        $this->actingInCompany($admin, $company)->patch(route('sales.cancel', $sale))->assertRedirect();

        $this->assertSame(0, CommissionEntry::allCompanies()->count());
    }

    public function test_anular_no_borra_una_comision_ya_liquidada(): void
    {
        $company = $this->company();
        $this->warehouse($company);
        $this->openCash($company);
        $barber = $this->barber($company);
        $service = Service::factory()->create(['company_id' => $company->id, 'price' => 100]);
        $this->rule($company, ['value' => 40]);

        $sale = $this->sell($company, $barber, [['type' => 'servicio', 'id' => $service->id, 'price' => 100]]);

        $user = $this->userInCompany($company, ['commissions.view', 'commissions.create', 'sales.view', 'sales.delete']);

        $this->actingInCompany($user, $company)
            ->post(route('commissions.settle', $barber), [
                'period_start' => now()->subDay()->toDateString(),
                'period_end' => now()->addDay()->toDateString(),
            ])->assertRedirect();

        $this->actingInCompany($user, $company)->patch(route('sales.cancel', $sale))->assertRedirect();

        // Ese dinero ya se pagó: borrarlo descuadraría la liquidación.
        $this->assertSame(1, CommissionEntry::allCompanies()->count());
    }

    // ── Liquidación ─────────────────────────────────────────────────────────

    public function test_liquidar_paga_comisiones_y_propinas(): void
    {
        $company = $this->company();
        $this->warehouse($company);
        $this->openCash($company);
        $barber = $this->barber($company);
        $service = Service::factory()->create(['company_id' => $company->id, 'price' => 100]);
        $this->rule($company, ['value' => 40]);

        $this->sell($company, $barber, [['type' => 'servicio', 'id' => $service->id, 'price' => 100]], tip: 15);

        $user = $this->userInCompany($company, ['commissions.view', 'commissions.create']);

        $this->actingInCompany($user, $company)
            ->post(route('commissions.settle', $barber), [
                'period_start' => now()->subDay()->toDateString(),
                'period_end' => now()->addDay()->toDateString(),
                'include_tips' => 1,
            ])->assertRedirect();

        $settlement = CommissionSettlement::allCompanies()->firstOrFail();

        $this->assertSame('LIQ-0001', $settlement->number);
        $this->assertSame('40.00', $settlement->commissions_total);
        $this->assertSame('15.00', $settlement->tips_total);
        $this->assertSame('55.00', $settlement->total);
        $this->assertSame(1, $settlement->entries_count);
    }

    public function test_se_puede_liquidar_sin_incluir_propinas(): void
    {
        $company = $this->company();
        $this->warehouse($company);
        $this->openCash($company);
        $barber = $this->barber($company);
        $service = Service::factory()->create(['company_id' => $company->id, 'price' => 100]);
        $this->rule($company, ['value' => 40]);

        $this->sell($company, $barber, [['type' => 'servicio', 'id' => $service->id, 'price' => 100]], tip: 15);

        $user = $this->userInCompany($company, ['commissions.view', 'commissions.create']);

        $this->actingInCompany($user, $company)
            ->post(route('commissions.settle', $barber), [
                'period_start' => now()->subDay()->toDateString(),
                'period_end' => now()->addDay()->toDateString(),
            ])->assertRedirect();

        $this->assertSame('0.00', CommissionSettlement::allCompanies()->first()->tips_total);
    }

    public function test_liquidar_saca_el_dinero_de_la_caja(): void
    {
        $company = $this->company();
        $this->warehouse($company);
        $session = $this->openCash($company);
        $barber = $this->barber($company);
        $service = Service::factory()->create(['company_id' => $company->id, 'price' => 100]);
        $this->rule($company, ['value' => 40]);

        $this->sell($company, $barber, [['type' => 'servicio', 'id' => $service->id, 'price' => 100]]);

        $user = $this->userInCompany($company, ['commissions.view', 'commissions.create']);

        $this->actingInCompany($user, $company)
            ->post(route('commissions.settle', $barber), [
                'period_start' => now()->subDay()->toDateString(),
                'period_end' => now()->addDay()->toDateString(),
            ])->assertRedirect();

        $egreso = CashMovement::allCompanies()->where('type', 'egreso')->firstOrFail();

        $this->assertSame('40.00', $egreso->amount);
        $this->assertSame($session->id, $egreso->cash_session_id);
    }

    public function test_una_comision_no_se_liquida_dos_veces(): void
    {
        $company = $this->company();
        $this->warehouse($company);
        $this->openCash($company);
        $barber = $this->barber($company);
        $service = Service::factory()->create(['company_id' => $company->id, 'price' => 100]);
        $this->rule($company, ['value' => 40]);

        $this->sell($company, $barber, [['type' => 'servicio', 'id' => $service->id, 'price' => 100]]);

        $user = $this->userInCompany($company, ['commissions.view', 'commissions.create']);
        $period = [
            'period_start' => now()->subDay()->toDateString(),
            'period_end' => now()->addDay()->toDateString(),
        ];

        $this->actingInCompany($user, $company)
            ->post(route('commissions.settle', $barber), $period)->assertRedirect();

        $this->actingInCompany($user, $company)
            ->post(route('commissions.settle', $barber), $period)->assertSessionHasErrors('error');

        $this->assertSame(1, CommissionSettlement::allCompanies()->count());
    }

    public function test_sin_turno_abierto_no_se_liquida(): void
    {
        $company = $this->company();
        $this->warehouse($company);
        $session = $this->openCash($company);
        $barber = $this->barber($company);
        $service = Service::factory()->create(['company_id' => $company->id, 'price' => 100]);
        $this->rule($company, ['value' => 40]);

        $this->sell($company, $barber, [['type' => 'servicio', 'id' => $service->id, 'price' => 100]]);

        $session->update(['status' => 'cerrada', 'closed_at' => now()]);

        $user = $this->userInCompany($company, ['commissions.view', 'commissions.create']);

        $this->actingInCompany($user, $company)
            ->post(route('commissions.settle', $barber), [
                'period_start' => now()->subDay()->toDateString(),
                'period_end' => now()->addDay()->toDateString(),
            ])->assertSessionHasErrors('error');
    }

    // ── Aislamiento y permisos ──────────────────────────────────────────────

    public function test_las_comisiones_se_aislan_por_empresa(): void
    {
        $mia = $this->company();
        $ajena = $this->company();
        $barberoAjeno = $this->barber($ajena, 'Barbero Ajeno');

        $user = $this->userInCompany($mia, ['commissions.view']);

        $this->actingInCompany($user, $mia)
            ->get(route('commissions.show', $barberoAjeno))
            ->assertNotFound();
    }

    public function test_las_comisiones_requieren_el_modulo(): void
    {
        $sinComisiones = $this->companyWithPlan(planAttributes: ['features' => ['pos']]);
        $user = $this->userInCompany($sinComisiones, ['commissions.view'], 'con-comisiones');

        $this->actingInCompany($user, $sinComisiones)->get(route('commissions.index'))->assertForbidden();
    }

    public function test_sin_permiso_no_se_liquida(): void
    {
        $company = $this->company();
        $barber = $this->barber($company);
        $user = $this->userInCompany($company, ['commissions.view']);   // solo consulta

        $this->actingInCompany($user, $company)->get(route('commissions.index'))->assertOk();

        $this->actingInCompany($user, $company)
            ->post(route('commissions.settle', $barber), [
                'period_start' => now()->toDateString(),
                'period_end' => now()->toDateString(),
            ])->assertForbidden();
    }

    public function test_no_se_admite_un_porcentaje_mayor_que_cien(): void
    {
        $company = $this->company();
        $user = $this->userInCompany($company, ['commission_rules.view', 'commission_rules.create']);

        $this->actingInCompany($user, $company)
            ->post(route('commission-rules.store'), [
                'applies_to' => 'servicios',
                'type' => 'porcentaje',
                'value' => 150,
            ])->assertSessionHasErrors('value');
    }
}
