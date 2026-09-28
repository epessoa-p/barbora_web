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
