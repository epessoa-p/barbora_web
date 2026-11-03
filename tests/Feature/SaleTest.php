<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Caja;
use App\Models\Cargo;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\Client;
use App\Models\Company;
use App\Models\Personal;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Service;
use App\Models\Warehouse;
use App\Support\StockManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ventas: el módulo que cierra el ciclo.
 *
 * Lo que de verdad hay que comprobar aquí no es el CRUD, sino que una venta
 * mueva a la vez las tres piezas: stock, caja y cita.
 */
class SaleTest extends TestCase
{
    use RefreshDatabase;

    /** Empresa con los cuatro módulos que toca una venta. */
    protected function company(array $features = ['pos', 'caja', 'inventario', 'agenda']): Company
    {
        return $this->companyWithPlan(planAttributes: ['features' => $features]);
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
            'balance' => 0,
            'active' => true,
        ]);

        return CashSession::create([
            'company_id' => $company->id,
            'caja_id' => $caja->id,
            'status' => 'abierta',
            'opened_at' => now(),
            'opening_amount' => 100,
        ]);
    }

    protected function service(Company $company, float $price = 50): Service
    {
        return Service::factory()->create([
            'company_id' => $company->id,
            'price' => $price,
            'duration_minutes' => 30,
        ]);
    }

    protected function product(Company $company, float $price = 60, float $stock = 10): Product
    {
        $product = Product::create([
            'company_id' => $company->id,
            'name' => 'Producto '.fake()->unique()->numerify('###'),
            'unit' => 'unidad',
            'cost_price' => 30,
            'sale_price' => $price,
            'min_stock' => 0,
            'is_sellable' => true,
            'track_stock' => true,
            'active' => true,
        ]);

        if ($stock > 0) {
            app(StockManager::class)->receive($product, $this->warehouseOf($product->company_id), $stock);
        }

        return $product;
    }

    protected function warehouseOf(int $companyId): Warehouse
    {
        return Warehouse::allCompanies()->where('company_id', $companyId)->firstOrFail();
    }

    // ── Cobro ───────────────────────────────────────────────────────────────

    public function test_se_cobra_una_venta_de_servicios(): void
    {
        $company = $this->company();
        $this->warehouse($company);
        $this->openCash($company);
        $service = $this->service($company, 50);
        $user = $this->userInCompany($company, ['sales.view', 'sales.create']);

        $this->actingInCompany($user, $company)
            ->post(route('sales.store'), [
                'items' => [['type' => 'servicio', 'id' => $service->id, 'quantity' => 1]],
                'payments' => [['payment_method' => 'efectivo', 'amount' => 50]],
            ])
            ->assertRedirect();

        $sale = Sale::allCompanies()->with('items')->firstOrFail();

        $this->assertSame('V-000001', $sale->number);
        $this->assertSame('pagada', $sale->status);
        $this->assertSame('50.00', $sale->total);
        $this->assertCount(1, $sale->items);
    }

    public function test_el_precio_lo_pone_el_catalogo_no_el_formulario(): void
    {
        $company = $this->company();
        $this->warehouse($company);
        $this->openCash($company);
        $service = $this->service($company, 50);
        $user = $this->userInCompany($company, ['sales.view', 'sales.create']);

        // Aunque el navegador mande otro precio, manda el del catálogo.
        $this->actingInCompany($user, $company)
            ->post(route('sales.store'), [
                'items' => [['type' => 'servicio', 'id' => $service->id, 'quantity' => 1, 'unit_price' => 1]],
                'payments' => [['payment_method' => 'efectivo', 'amount' => 50]],
            ])
            ->assertRedirect();

        $this->assertSame('50.00', Sale::allCompanies()->first()->total);
    }

    public function test_los_pagos_tienen_que_cuadrar_con_el_total(): void
    {
        $company = $this->company();
        $this->warehouse($company);
        $this->openCash($company);
        $service = $this->service($company, 50);
        $user = $this->userInCompany($company, ['sales.view', 'sales.create']);

        $this->actingInCompany($user, $company)
            ->post(route('sales.store'), [
                'items' => [['type' => 'servicio', 'id' => $service->id, 'quantity' => 1]],
                'payments' => [['payment_method' => 'efectivo', 'amount' => 30]],
            ])
            ->assertSessionHasErrors('payments');

        $this->assertSame(0, Sale::allCompanies()->count());
    }

    public function test_se_admite_el_pago_mixto(): void
    {
        $company = $this->company();
        $this->warehouse($company);
        $session = $this->openCash($company);
        $service = $this->service($company, 100);
        $user = $this->userInCompany($company, ['sales.view', 'sales.create']);

        $this->actingInCompany($user, $company)
            ->post(route('sales.store'), [
                'items' => [['type' => 'servicio', 'id' => $service->id, 'quantity' => 1]],
                'payments' => [
                    ['payment_method' => 'efectivo', 'amount' => 60],
                    ['payment_method' => 'tarjeta', 'amount' => 40],
                ],
            ])
            ->assertRedirect();

        $sale = Sale::allCompanies()->with('payments')->firstOrFail();

        $this->assertCount(2, $sale->payments);
        $this->assertSame(60.0, $sale->cashAmount());   // solo el efectivo va al cajón
    }

    public function test_el_descuento_y_la_propina_entran_en_el_total(): void
    {
        $company = $this->company();
        $this->warehouse($company);
        $this->openCash($company);
        $service = $this->service($company, 100);
        $user = $this->userInCompany($company, ['sales.view', 'sales.create']);

        // 100 − 20 + 15 = 95
        $this->actingInCompany($user, $company)
            ->post(route('sales.store'), [
                'items' => [['type' => 'servicio', 'id' => $service->id, 'quantity' => 1]],
                'discount' => 20,
                'tip' => 15,
                'payments' => [['payment_method' => 'efectivo', 'amount' => 95]],
            ])
            ->assertRedirect();

        $sale = Sale::allCompanies()->firstOrFail();

        $this->assertSame('100.00', $sale->subtotal);
        $this->assertSame('95.00', $sale->total);
        $this->assertSame('15.00', $sale->tip);
    }

    public function test_el_descuento_no_puede_superar_el_subtotal(): void
    {
        $company = $this->company();
        $this->warehouse($company);
        $this->openCash($company);
        $service = $this->service($company, 50);
        $user = $this->userInCompany($company, ['sales.view', 'sales.create']);

        $this->actingInCompany($user, $company)
            ->post(route('sales.store'), [
                'items' => [['type' => 'servicio', 'id' => $service->id, 'quantity' => 1]],
                'discount' => 80,
                'payments' => [['payment_method' => 'efectivo', 'amount' => 1]],
            ])
            ->assertSessionHasErrors('discount');
    }

    // ── Enlace con inventario ───────────────────────────────────────────────

    public function test_vender_un_producto_descuenta_stock(): void
    {
        $company = $this->company();
        $this->warehouse($company);
        $this->openCash($company);
        $product = $this->product($company, 60, 10);
        $user = $this->userInCompany($company, ['sales.view', 'sales.create']);

        $this->actingInCompany($user, $company)
            ->post(route('sales.store'), [
                'items' => [['type' => 'producto', 'id' => $product->id, 'quantity' => 3]],
                'payments' => [['payment_method' => 'efectivo', 'amount' => 180]],
            ])
            ->assertRedirect();

        $this->assertSame(7.0, $this->inCompany($company, fn () => $product->load('stocks')->totalStock()));
    }

    public function test_no_se_vende_mas_producto_del_que_hay(): void
    {
        $company = $this->company();
        $this->warehouse($company);
        $this->openCash($company);
        $product = $this->product($company, 60, 2);
        $user = $this->userInCompany($company, ['sales.view', 'sales.create']);

        $this->actingInCompany($user, $company)
            ->post(route('sales.store'), [
                'items' => [['type' => 'producto', 'id' => $product->id, 'quantity' => 5]],
                'payments' => [['payment_method' => 'efectivo', 'amount' => 300]],
            ])
            ->assertSessionHasErrors('quantity');

        // La transacción se deshace entera: ni venta ni stock movido.
        $this->assertSame(0, Sale::allCompanies()->count());
        $this->assertSame(2.0, $this->inCompany($company, fn () => $product->load('stocks')->totalStock()));
    }

    public function test_un_servicio_no_toca_el_stock(): void
    {
        $company = $this->company();
        $this->warehouse($company);
        $this->openCash($company);
        $service = $this->service($company);
        $product = $this->product($company, 60, 10);
        $user = $this->userInCompany($company, ['sales.view', 'sales.create']);

        $this->actingInCompany($user, $company)
            ->post(route('sales.store'), [
                'items' => [['type' => 'servicio', 'id' => $service->id, 'quantity' => 1]],
                'payments' => [['payment_method' => 'efectivo', 'amount' => 50]],
            ])
            ->assertRedirect();

        $this->assertSame(10.0, $this->inCompany($company, fn () => $product->load('stocks')->totalStock()));
    }

    // ── Enlace con caja ─────────────────────────────────────────────────────

    public function test_una_venta_rapida_no_mueve_stock_y_queda_sin_costo(): void
    {
        $company = $this->company();
        $this->warehouse($company);
        $this->openCash($company);
        $product = $this->product($company, 60, 10);
        $user = $this->userInCompany($company, ['sales.view', 'sales.create']);

        $this->actingInCompany($user, $company)->post(route('sales.store'), [
            'items' => [
                ['type' => 'producto', 'id' => $product->id, 'quantity' => 1],
                // Un producto que no está en el inventario: nombre y precio a mano.
                ['type' => 'rapido', 'name' => 'gel fijador', 'price' => 25, 'quantity' => 2],
            ],
            'payments' => [['payment_method' => 'efectivo', 'amount' => 110]],
        ])->assertRedirect();

        $sale = Sale::allCompanies()->with('items')->firstOrFail();
        $quick = $sale->items->firstWhere('product_id', null);

        $this->assertSame('110.00', $sale->total);
        $this->assertSame('GEL FIJADOR', $quick->description);
        $this->assertNull($quick->unit_cost);
        // Solo el producto con ficha descontó stock.
        $this->assertSame(9.0, $product->totalStock());

        // Sin nombre o sin precio, no.
        $this->actingInCompany($user, $company)->post(route('sales.store'), [
            'items' => [['type' => 'rapido', 'quantity' => 1]],
            'payments' => [['payment_method' => 'efectivo', 'amount' => 10]],
        ])->assertSessionHasErrors(['items.0.name', 'items.0.price']);
    }

    public function test_el_reporte_de_ganancias_separa_ventas_servicios_y_ventas_rapidas(): void
    {
        $company = $this->company(['pos', 'caja', 'inventario', 'agenda', 'estadisticas']);
        $this->warehouse($company);
        $this->openCash($company);
        $product = $this->product($company, 60, 10);   // costo 30
        $service = $this->service($company, 50);
        $user = $this->userInCompany($company, ['sales.view', 'sales.create', 'reports.view']);

        $this->actingInCompany($user, $company)->post(route('sales.store'), [
            'items' => [
                ['type' => 'producto', 'id' => $product->id, 'quantity' => 2],   // 120, costo 60
                ['type' => 'servicio', 'id' => $service->id, 'quantity' => 1],   // 50
                ['type' => 'rapido', 'name' => 'Gel', 'price' => 30, 'quantity' => 1],
            ],
            'payments' => [['payment_method' => 'efectivo', 'amount' => 200]],
        ])->assertRedirect();

        $period = new \App\Support\ReportPeriod(now()->toImmutable()->startOfDay(), now()->toImmutable()->endOfDay());
        $report = fn (string $scope, bool $quick) => $this->inCompany($company, fn () => app(\App\Support\Reports\ProfitReport::class)->for($period, $scope, $quick));

        $both = $report('ambos', false);
        $this->assertSame(170.0, $both['summary']['revenue'], 'Sin la venta rápida.');
        $this->assertSame(60.0, $both['products']['profit']);
        $this->assertSame(50.0, $both['services']['profit']);
        $this->assertSame(['revenue' => 30.0, 'count' => 1], $both['quick']);

        $withQuick = $report('ambos', true);
        $this->assertSame(200.0, $withQuick['summary']['revenue']);
        $this->assertSame(140.0, $withQuick['summary']['profit']);

        $this->assertSame(50.0, $report('servicios', false)['summary']['revenue']);
        $this->assertSame(120.0, $report('ventas', false)['summary']['revenue']);

        // La web y la app.
        $this->actingInCompany($user, $company)->get(route('reports.earnings', ['scope' => 'ventas', 'quick' => 1]))
            ->assertOk()->assertSee('Ganancia por lo vendido')->assertSee('Unir ventas rápidas');

        \Laravel\Sanctum\Sanctum::actingAs($user);
        $this->getJson('/api/v1/reports/profit?preset=hoy&quick=1', ['X-Company-Id' => (string) $company->id])
            ->assertOk()
            ->assertJsonPath('summary.revenue', 200.0)
            ->assertJsonPath('include_quick', true)
            ->assertJsonPath('sales.0.revenue', 200.0);
    }

    public function test_el_analisis_compara_con_el_mismo_tramo_y_el_inventario_se_valoriza(): void
    {
        $company = $this->company(['pos', 'caja', 'inventario', 'agenda', 'estadisticas']);
        $this->warehouse($company);
        $this->openCash($company);
        $product = $this->product($company, 60, 10);   // costo 30, 10 en stock
        $service = $this->service($company, 50);
        $user = $this->userInCompany($company, ['sales.view', 'sales.create', 'reports.view']);

        $this->actingInCompany($user, $company)->post(route('sales.store'), [
            'items' => [
                ['type' => 'producto', 'id' => $product->id, 'quantity' => 2],
                ['type' => 'servicio', 'id' => $service->id, 'quantity' => 1],
            ],
            'payments' => [['payment_method' => 'efectivo', 'amount' => 170]],
        ])->assertRedirect();

        $r = $this->inCompany($company, fn () => app(\App\Support\Reports\AnalysisReport::class)->for());

        $this->assertSame(50.0, $r['series']['servicios']['amount']['week']);
        $this->assertSame(120.0, $r['series']['ventas']['amount']['month']);
        $this->assertSame(2.0, $r['series']['ventas']['count']['week']);
        $this->assertNull($r['series']['ventas']['amount']['week_change'], 'Sin nada antes, no hay %.');
        $this->assertCount(7, $r['series']['servicios']['amount']['daily']);
        $this->assertCount(8, $r['series']['servicios']['amount']['weeks']);
        $this->assertCount(6, $r['series']['servicios']['amount']['months']);
        $this->assertSame(120.0, collect($r['series']['ventas']['amount']['weeks'])->last()['value']);
        $this->assertSame(50.0, $r['top']['services'][0]['amount']);
        $this->assertSame(0.0, $r['series']['compras']['amount']['month']);

        // Inventario: quedan 8 unidades, a costo 30 y venta 60.
        $inv = $this->inCompany($company, fn () => app(\App\Support\Reports\ProductsReport::class)->inventory());
        $this->assertSame(['products' => 1, 'units' => 8.0, 'cost' => 240.0, 'sale' => 480.0, 'potential' => 240.0], $inv);

        foreach (['servicios', 'ventas', 'compras', 'top'] as $tab) {
            $this->actingInCompany($user, $company)->get(route('reports.analysis', ['tab' => $tab, 'metric' => 'cantidad']))
                ->assertOk()->assertSee('Análisis');
        }
        $this->actingInCompany($user, $company)->get(route('reports.products'))
            ->assertOk()->assertSee('Ganancia potencial');

        \Laravel\Sanctum\Sanctum::actingAs($user);
        $this->getJson('/api/v1/reports/analysis', ['X-Company-Id' => (string) $company->id])
            ->assertOk()
            ->assertJsonPath('series.servicios.amount.week', 50.0);
        $this->getJson('/api/v1/reports/productos?preset=hoy', ['X-Company-Id' => (string) $company->id])
            ->assertOk()
            ->assertJsonPath('inventory.potential', 240.0);
    }

    public function test_la_venta_entra_en_el_turno_de_caja(): void
    {
        $company = $this->company();
        $this->warehouse($company);
        $session = $this->openCash($company);
        $service = $this->service($company, 50);
        $user = $this->userInCompany($company, ['sales.view', 'sales.create']);

        $this->actingInCompany($user, $company)
            ->post(route('sales.store'), [
                'items' => [['type' => 'servicio', 'id' => $service->id, 'quantity' => 1]],
                'payments' => [['payment_method' => 'efectivo', 'amount' => 50]],
            ])
            ->assertRedirect();

        $movement = CashMovement::allCompanies()->firstOrFail();

        $this->assertSame('ingreso', $movement->type);
        $this->assertSame('50.00', $movement->amount);
        $this->assertSame($session->id, $movement->cash_session_id);
        $this->assertSame($session->id, Sale::allCompanies()->first()->cash_session_id);
    }

    public function test_la_pantalla_de_cobro_ofrece_pills_mixto_y_cliente_nuevo(): void
    {
        $company = $this->company(['pos', 'caja', 'inventario', 'agenda', 'clientes']);
        $user = $this->userInCompany($company, ['sales.view', 'sales.create', 'clients.create']);

        $this->actingInCompany($user, $company)
            ->get(route('sales.create'))
            ->assertOk()
            ->assertSee('id="pill-efectivo"', false)
            ->assertSee('id="mixed-payment"', false)
            ->assertSee('new-client-modal');
    }

    public function test_la_sucursal_propuesta_es_la_del_usuario_y_se_oculta_si_hay_una(): void
    {
        $company = $this->company();
        $norte = Branch::factory()->create(['company_id' => $company->id, 'name' => 'NORTE']);
        $user = $this->userInCompany($company, ['sales.view', 'sales.create']);

        // Una sola sucursal: no hay selector, va oculta.
        $this->actingInCompany($user, $company)
            ->get(route('sales.create'))
            ->assertDontSee('<select id="branch_id"', false)
            ->assertSee('<input type="hidden" name="branch_id" id="branch_id"'."\n".'           value="'.$norte->id.'"', false);

        // Con dos, se propone la de la ficha de personal del usuario.
        $sur = Branch::factory()->create(['company_id' => $company->id, 'name' => 'SUR']);
        Personal::withoutGlobalScopes()->create([
            'company_id' => $company->id, 'user_id' => $user->id, 'full_name' => 'CAJERO',
            'branch_id' => $sur->id, 'active' => true,
        ]);

        // Con dos sucursales en la empresa pero solo SUR asignada: sin selector, va SUR.
        $html = $this->actingInCompany($user, $company)->get(route('sales.create'))->getContent();
        $this->assertStringNotContainsString('<select id="branch_id"', $html);
        $this->assertMatchesRegularExpression('/name="branch_id" id="branch_id"\s+value="'.$sur->id.'"/', $html);
    }

    public function test_sin_sucursal_la_venta_va_a_la_del_usuario_si_tiene_caja_abierta(): void
    {
        $company = $this->company();
        $this->warehouse($company);
        $sur = Branch::factory()->create(['company_id' => $company->id]);
        $service = $this->service($company, 50);
        $user = $this->userInCompany($company, ['sales.view', 'sales.create']);
        Personal::withoutGlobalScopes()->create([
            'company_id' => $company->id, 'user_id' => $user->id, 'full_name' => 'CAJERO',
            'branch_id' => $sur->id, 'active' => true,
        ]);

        $sale = fn () => $this->actingInCompany($user, $company)->post(route('sales.store'), [
            'items' => [['type' => 'servicio', 'id' => $service->id, 'quantity' => 1]],
            'payments' => [['payment_method' => 'efectivo', 'amount' => 50]],
        ])->assertRedirect();

        // Sin caja abierta en su sucursal: decide el turno abierto, como antes.
        $other = $this->openCash($company);
        $sale();
        $this->assertSame($other->id, Sale::allCompanies()->latest('id')->first()->cash_session_id);

        // Con caja abierta en su sucursal: la venta es de esa sucursal.
        $mine = $this->openCash($company);
        $mine->caja->update(['branch_id' => $sur->id]);
        $mine->update(['branch_id' => $sur->id]);
        $sale();
        $last = Sale::allCompanies()->latest('id')->first();
        $this->assertSame($sur->id, $last->branch_id);
        $this->assertSame($mine->id, $last->cash_session_id);
    }

    public function test_sin_turno_abierto_no_se_puede_cobrar(): void
    {
        $company = $this->company();
        $this->warehouse($company);
        $service = $this->service($company, 50);
        $user = $this->userInCompany($company, ['sales.view', 'sales.create']);

        $this->actingInCompany($user, $company)
            ->post(route('sales.store'), [
                'items' => [['type' => 'servicio', 'id' => $service->id, 'quantity' => 1]],
                'payments' => [['payment_method' => 'efectivo', 'amount' => 50]],
            ])
            ->assertSessionHasErrors('error');

        $this->assertSame(0, Sale::allCompanies()->count());
    }

    public function test_sin_modulo_de_caja_se_vende_igual(): void
    {
        // Un plan con POS pero sin caja no debe exigir turno abierto.
        $company = $this->company(['pos', 'inventario']);
        $this->warehouse($company);
        $service = $this->service($company, 50);
        $user = $this->userInCompany($company, ['sales.view', 'sales.create']);

        $this->actingInCompany($user, $company)
            ->post(route('sales.store'), [
                'items' => [['type' => 'servicio', 'id' => $service->id, 'quantity' => 1]],
                'payments' => [['payment_method' => 'efectivo', 'amount' => 50]],
            ])
            ->assertRedirect();

        $this->assertNull(Sale::allCompanies()->first()->cash_session_id);
    }

    // ── Enlace con la agenda ────────────────────────────────────────────────

    public function test_cobrar_una_cita_la_marca_como_atendida(): void
    {
        $company = $this->company();
        $this->warehouse($company);
        $this->openCash($company);
        $service = $this->service($company, 50);
        $client = Client::factory()->create(['company_id' => $company->id]);

        $cargo = Cargo::create(['company_id' => $company->id, 'name' => 'Barbero', 'active' => true]);
        $barber = Personal::create([
            'company_id' => $company->id, 'cargo_id' => $cargo->id,
            'full_name' => 'Barbero Uno', 'bookable' => true, 'active' => true,
        ]);

        $appointment = Appointment::create([
            'company_id' => $company->id,
            'personal_id' => $barber->id,
            'client_id' => $client->id,
            'starts_at' => now()->addHour(),
            'ends_at' => now()->addHours(2),
            'status' => 'confirmada',
        ]);

        $user = $this->userInCompany($company, ['sales.view', 'sales.create']);

        $this->actingInCompany($user, $company)
            ->post(route('sales.store'), [
                'appointment_id' => $appointment->id,
                'client_id' => $client->id,
                'personal_id' => $barber->id,
                'items' => [['type' => 'servicio', 'id' => $service->id, 'quantity' => 1]],
                'payments' => [['payment_method' => 'efectivo', 'amount' => 50]],
            ])
            ->assertRedirect();

        $this->assertSame('atendida', $appointment->refresh()->status);
    }

    // ── Anulación ───────────────────────────────────────────────────────────

    public function test_anular_devuelve_el_stock_y_saca_de_caja(): void
    {
        $company = $this->company();
        $this->warehouse($company);
        $this->openCash($company);
        $product = $this->product($company, 60, 10);
        $user = $this->userInCompany($company, ['sales.view', 'sales.create', 'sales.delete']);

        $this->actingInCompany($user, $company)
            ->post(route('sales.store'), [
                'items' => [['type' => 'producto', 'id' => $product->id, 'quantity' => 3]],
                'payments' => [['payment_method' => 'efectivo', 'amount' => 180]],
            ])
            ->assertRedirect();

        $sale = Sale::allCompanies()->firstOrFail();
        $this->assertSame(7.0, $this->inCompany($company, fn () => $product->load('stocks')->totalStock()));

        $this->actingInCompany($user, $company)
            ->patch(route('sales.cancel', $sale), ['cancel_reason' => 'Cliente se arrepintió'])
            ->assertRedirect();

        $sale->refresh();

        $this->assertSame('anulada', $sale->status);
        $this->assertSame('Cliente se arrepintió', $sale->cancel_reason);
        $this->assertSame(10.0, $this->inCompany($company, fn () => $product->load('stocks')->totalStock()));
        $this->assertSame(1, CashMovement::allCompanies()->where('type', 'egreso')->count());
    }

    public function test_una_venta_anulada_no_se_anula_dos_veces(): void
    {
        $company = $this->company();
        $this->warehouse($company);
        $this->openCash($company);
        $service = $this->service($company, 50);
        $user = $this->userInCompany($company, ['sales.view', 'sales.create', 'sales.delete']);

        $this->actingInCompany($user, $company)
            ->post(route('sales.store'), [
                'items' => [['type' => 'servicio', 'id' => $service->id, 'quantity' => 1]],
                'payments' => [['payment_method' => 'efectivo', 'amount' => 50]],
            ])->assertRedirect();

        $sale = Sale::allCompanies()->firstOrFail();

        $this->actingInCompany($user, $company)->patch(route('sales.cancel', $sale))->assertRedirect();
        $this->actingInCompany($user, $company)->patch(route('sales.cancel', $sale))->assertSessionHasErrors('error');
    }

    public function test_las_anuladas_no_cuentan_en_el_resumen(): void
    {
        $company = $this->company();
        $this->warehouse($company);
        $this->openCash($company);
        $service = $this->service($company, 50);
        $user = $this->userInCompany($company, ['sales.view', 'sales.create', 'sales.delete']);

        foreach ([1, 2] as $_) {
            $this->actingInCompany($user, $company)
                ->post(route('sales.store'), [
                    'items' => [['type' => 'servicio', 'id' => $service->id, 'quantity' => 1]],
                    'payments' => [['payment_method' => 'efectivo', 'amount' => 50]],
                ])->assertRedirect();
        }

        $first = Sale::allCompanies()->orderBy('id')->first();
        $this->actingInCompany($user, $company)->patch(route('sales.cancel', $first))->assertRedirect();

        $this->assertSame(1, $this->inCompany($company, fn () => Sale::paid()->count()));
    }

    // ── Numeración, aislamiento y permisos ──────────────────────────────────

    public function test_la_numeracion_es_correlativa_por_empresa(): void
    {
        $a = $this->company();
        $b = $this->company();

        foreach ([$a, $b] as $company) {
            $this->warehouse($company);
            $this->openCash($company);
            $service = $this->service($company, 50);
            $user = $this->userInCompany($company, ['sales.view', 'sales.create']);

            foreach ([1, 2] as $_) {
                $this->actingInCompany($user, $company)
                    ->post(route('sales.store'), [
                        'items' => [['type' => 'servicio', 'id' => $service->id, 'quantity' => 1]],
                        'payments' => [['payment_method' => 'efectivo', 'amount' => 50]],
                    ])->assertRedirect();
            }
        }

        // Cada empresa lleva su propia numeración desde V-000001.
        $this->assertSame(
            ['V-000001', 'V-000002'],
            Sale::allCompanies()->where('company_id', $a->id)->orderBy('id')->pluck('number')->all()
        );
        $this->assertSame(
            ['V-000001', 'V-000002'],
            Sale::allCompanies()->where('company_id', $b->id)->orderBy('id')->pluck('number')->all()
        );
    }

    public function test_las_ventas_se_aislan_por_empresa(): void
    {
        $mia = $this->company();
        $ajena = $this->company();

        $this->warehouse($ajena);
        $this->openCash($ajena);
        $service = $this->service($ajena, 50);
        $otro = $this->userInCompany($ajena, ['sales.view', 'sales.create'], 'ajeno');

        $this->actingInCompany($otro, $ajena)
            ->post(route('sales.store'), [
                'items' => [['type' => 'servicio', 'id' => $service->id, 'quantity' => 1]],
                'payments' => [['payment_method' => 'efectivo', 'amount' => 50]],
            ])->assertRedirect();

        $ventaAjena = Sale::allCompanies()->firstOrFail();
        $user = $this->userInCompany($mia, ['sales.view']);

        $this->actingInCompany($user, $mia)->get(route('sales.show', $ventaAjena))->assertNotFound();
    }

    public function test_las_ventas_requieren_el_modulo_pos(): void
    {
        $sinPos = $this->company(['agenda', 'caja']);
        $user = $this->userInCompany($sinPos, ['sales.view'], 'con-ventas');

        $this->actingInCompany($user, $sinPos)->get(route('sales.index'))->assertForbidden();
    }

    public function test_sin_permiso_no_se_anula(): void
    {
        $company = $this->company();
        $this->warehouse($company);
        $this->openCash($company);
        $service = $this->service($company, 50);
        $vendedor = $this->userInCompany($company, ['sales.view', 'sales.create'], 'vendedor');

        $this->actingInCompany($vendedor, $company)
            ->post(route('sales.store'), [
                'items' => [['type' => 'servicio', 'id' => $service->id, 'quantity' => 1]],
                'payments' => [['payment_method' => 'efectivo', 'amount' => 50]],
            ])->assertRedirect();

        $sale = Sale::allCompanies()->firstOrFail();

        $this->actingInCompany($vendedor, $company)
            ->patch(route('sales.cancel', $sale))
            ->assertForbidden();
    }

    public function test_el_comprobante_se_puede_ver(): void
    {
        $company = $this->company();
        $this->warehouse($company);
        $this->openCash($company);
        $service = $this->service($company, 50);
        $user = $this->userInCompany($company, ['sales.view', 'sales.create']);

        $this->actingInCompany($user, $company)
            ->post(route('sales.store'), [
                'items' => [['type' => 'servicio', 'id' => $service->id, 'quantity' => 1]],
                'payments' => [['payment_method' => 'efectivo', 'amount' => 50]],
            ])->assertRedirect();

        $sale = Sale::allCompanies()->firstOrFail();

        $this->actingInCompany($user, $company)
            ->get(route('sales.receipt', $sale))
            ->assertOk()
            ->assertSee('V-000001');
    }
}
