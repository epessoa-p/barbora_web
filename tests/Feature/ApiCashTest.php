<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Caja;
use App\Models\CashSession;
use App\Models\Client;
use App\Models\Company;
use App\Models\Personal;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Service;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\StockManager;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Caja y cobro desde el móvil.
 *
 * Lo que de verdad hay que proteger aquí es el dinero. Un móvil pierde la red a
 * media transacción, así que el primer bloque de tests es la idempotencia: que
 * un reintento devuelva la venta original en lugar de cobrar dos veces.
 */
class ApiCashTest extends TestCase
{
    use RefreshDatabase;

    protected const FEATURES = ['caja', 'pos', 'inventario', 'agenda', 'clientes', 'comisiones'];

    protected Company $company;
    protected Caja $caja;
    protected User $cajero;
    protected Personal $barbero;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = $this->companyWithPlan(planAttributes: ['features' => self::FEATURES]);
        Branch::factory()->create(['company_id' => $this->company->id]);

        $this->caja = $this->inCompany($this->company, fn () => Caja::create([
            'company_id' => $this->company->id,
            'name' => 'Caja principal',
            'balance' => 0,
            'active' => true,
        ]));

        $this->cajero = $this->userInCompany($this->company, [
            'cash.view', 'cash.create', 'cash.edit',
            'sales.view', 'sales.create',
        ], 'cajero_api');

        $this->barbero = $this->inCompany($this->company, fn () => Personal::create([
            'company_id' => $this->company->id,
            'full_name' => 'Tania Molina',
            'active' => true,
            'bookable' => true,
        ]));
    }

    /* ---------------------------------------------------------------------
     | Idempotencia: que un reintento no cobre dos veces
     |--------------------------------------------------------------------- */

    public function test_el_mismo_cobro_reintentado_no_cobra_dos_veces(): void
    {
        $this->openSession();
        $service = $this->service(100);

        Sanctum::actingAs($this->cajero);

        $payload = $this->salePayload($service, 100);
        $key = 'cobro-abc-123';

        $first = $this->withHeaders($this->headers(['Idempotency-Key' => $key]))
            ->postJson('/api/v1/sales', $payload)
            ->assertCreated();

        // El teléfono perdió la respuesta y reintenta exactamente lo mismo.
        $second = $this->withHeaders($this->headers(['Idempotency-Key' => $key]))
            ->postJson('/api/v1/sales', $payload)
            ->assertCreated();

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertTrue($second->json('idempotent_replay'));

        $this->assertSame(1, $this->inCompany($this->company, fn () => Sale::count()),
            'Se cobró dos veces: eso es dinero de más al cliente.');
    }

    public function test_el_reintento_tampoco_duplica_el_stock_ni_la_caja(): void
    {
        $session = $this->openSession();
        $product = $this->product(salePrice: 60, stock: 10);

        Sanctum::actingAs($this->cajero);

        $payload = [
            'items' => [['type' => 'producto', 'id' => $product->id, 'quantity' => 2]],
            'payments' => [['payment_method' => 'efectivo', 'amount' => 120]],
        ];

        foreach ([1, 2] as $_) {
            $this->withHeaders($this->headers(['Idempotency-Key' => 'cobro-stock']))
                ->postJson('/api/v1/sales', $payload)
                ->assertCreated();
        }

        $stock = $this->inCompany(
            $this->company,
            fn () => \App\Models\Stock::where('product_id', $product->id)->value('quantity'),
        );

        $this->assertSame('8.00', $stock, 'El stock bajó dos veces.');
        $this->assertSame(1, $session->fresh()->movements()->where('type', 'ingreso')->count());
    }

    /**
     * El reintento tiene que devolver los importes como decimal, igual que el
     * cobro original.
     *
     * Es sutil y peligroso: si el cuerpo guardado se decodifica y se vuelve a
     * codificar, 100.0 se convierte en 100, y el «as double» del cliente Dart
     * revienta. Y pasaría sólo en el reintento, es decir, justo después de un
     * fallo de red: el peor momento para que la app se caiga.
     */
    public function test_el_reintento_conserva_los_decimales(): void
    {
        $this->openSession();
        $service = $this->service(100);

        Sanctum::actingAs($this->cajero);

        $payload = $this->salePayload($service, 100);
        $headers = $this->headers(['Idempotency-Key' => 'decimales']);

        $first = $this->withHeaders($headers)->postJson('/api/v1/sales', $payload);
        $second = $this->withHeaders($headers)->postJson('/api/v1/sales', $payload);

        $this->assertStringContainsString('"total":100.0', $first->getContent());
        $this->assertStringContainsString('"total":100.0', $second->getContent());
        $this->assertTrue($second->json('idempotent_replay'));
    }

    public function test_dos_cobros_distintos_con_claves_distintas_si_se_registran(): void
    {
        $this->openSession();
        $service = $this->service(100);

        Sanctum::actingAs($this->cajero);

        foreach (['cobro-1', 'cobro-2'] as $key) {
            $this->withHeaders($this->headers(['Idempotency-Key' => $key]))
                ->postJson('/api/v1/sales', $this->salePayload($service, 100))
                ->assertCreated();
        }

        $this->assertSame(2, $this->inCompany($this->company, fn () => Sale::count()));
    }

    public function test_reusar_la_clave_para_otro_cobro_se_rechaza(): void
    {
        $this->openSession();
        $barato = $this->service(50);
        $caro = $this->service(200);

        Sanctum::actingAs($this->cajero);

        $this->withHeaders($this->headers(['Idempotency-Key' => 'repetida']))
            ->postJson('/api/v1/sales', $this->salePayload($barato, 50))
            ->assertCreated();

        // Misma clave, cobro distinto: devolver la venta anterior en silencio
        // haría creer que se cobraron 200 cuando no se cobró nada.
        $this->withHeaders($this->headers(['Idempotency-Key' => 'repetida']))
            ->postJson('/api/v1/sales', $this->salePayload($caro, 200))
            ->assertStatus(422)
            ->assertJsonPath('error', 'idempotency_key_reused');

        $this->assertSame(1, $this->inCompany($this->company, fn () => Sale::count()));
    }

    public function test_sin_clave_se_cobra_igual(): void
    {
        $this->openSession();
        $service = $this->service(100);

        Sanctum::actingAs($this->cajero);

        // La cabecera no es obligatoria: un cliente sencillo debe funcionar.
        $this->withHeaders($this->headers())
            ->postJson('/api/v1/sales', $this->salePayload($service, 100))
            ->assertCreated();

        $this->assertSame(1, $this->inCompany($this->company, fn () => Sale::count()));
    }

    public function test_la_clave_de_otra_barberia_no_se_cruza(): void
    {
        $this->openSession();
        $service = $this->service(100);

        Sanctum::actingAs($this->cajero);
        $this->withHeaders($this->headers(['Idempotency-Key' => 'compartida']))
            ->postJson('/api/v1/sales', $this->salePayload($service, 100))
            ->assertCreated();

        // Otra barbería usando por casualidad la misma clave tiene que cobrar.
        $otra = $this->companyWithPlan(planAttributes: ['features' => self::FEATURES]);
        Branch::factory()->create(['company_id' => $otra->id]);
        $otroCajero = $this->userInCompany($otra, ['cash.create', 'sales.create', 'sales.view'], 'cajero_otra');
        $this->openSession($otra);
        $otroServicio = $this->service(70, $otra);

        Sanctum::actingAs($otroCajero);
        $this->withHeaders(['X-Company-Id' => (string) $otra->id, 'Idempotency-Key' => 'compartida'])
            ->postJson('/api/v1/sales', $this->salePayload($otroServicio, 70))
            ->assertCreated();

        $this->assertSame(1, $this->inCompany($otra, fn () => Sale::count()));
    }

    /* ---------------------------------------------------------------------
     | Turno de caja
     |--------------------------------------------------------------------- */

    public function test_se_abre_un_turno(): void
    {
        Sanctum::actingAs($this->cajero);

        $response = $this->withHeaders($this->headers())
            ->postJson('/api/v1/cash/session', [
                'caja_id' => $this->caja->id,
                'opening_amount' => 150,
            ])
            ->assertCreated();

        $this->assertTrue($response->json('data.is_open'));
        $this->assertSame(150.0, $response->json('data.opening_amount'));
        $this->assertSame(150.0, $response->json('data.totals.expected_cash'));
    }

    public function test_no_se_abren_dos_turnos_en_la_misma_caja(): void
    {
        $this->openSession();

        Sanctum::actingAs($this->cajero);

        $this->withHeaders($this->headers())
            ->postJson('/api/v1/cash/session', [
                'caja_id' => $this->caja->id,
                'opening_amount' => 100,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('caja_id');
    }

    public function test_sin_turno_abierto_se_dice_claramente(): void
    {
        Sanctum::actingAs($this->cajero);

        $this->withHeaders($this->headers())
            ->getJson('/api/v1/cash/session')
            ->assertOk()
            ->assertJsonPath('data', null);
    }

    public function test_el_cobro_entra_en_el_efectivo_esperado(): void
    {
        $this->openSession(openingAmount: 100);
        $service = $this->service(80);

        Sanctum::actingAs($this->cajero);

        $this->withHeaders($this->headers())
            ->postJson('/api/v1/sales', $this->salePayload($service, 80))
            ->assertCreated();

        $response = $this->withHeaders($this->headers())
            ->getJson('/api/v1/cash/session')
            ->assertOk();

        // Fondo 100 + 80 cobrados en efectivo.
        $this->assertSame(180.0, $response->json('data.totals.expected_cash'));
        $this->assertSame(80.0, $response->json('data.totals.cash_income'));
    }

    public function test_se_registra_un_movimiento_manual(): void
    {
        $this->openSession(openingAmount: 100);

        Sanctum::actingAs($this->cajero);

        $this->withHeaders($this->headers())
            ->postJson('/api/v1/cash/movements', [
                'type' => 'egreso',
                'payment_method' => 'efectivo',
                'concept' => 'Compra de toallas',
                'amount' => 35,
            ])
            ->assertCreated()
            ->assertJsonPath('session.totals.expected_cash', 65.0);
    }

    public function test_sin_turno_abierto_no_se_registran_movimientos(): void
    {
        Sanctum::actingAs($this->cajero);

        $this->withHeaders($this->headers())
            ->postJson('/api/v1/cash/movements', [
                'type' => 'egreso',
                'payment_method' => 'efectivo',
                'concept' => 'Algo',
                'amount' => 10,
            ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'no_open_session');
    }

    public function test_el_arqueo_guarda_la_diferencia(): void
    {
        $session = $this->openSession(openingAmount: 100);
        $service = $this->service(80);

        Sanctum::actingAs($this->cajero);
        $this->withHeaders($this->headers())
            ->postJson('/api/v1/sales', $this->salePayload($service, 80))
            ->assertCreated();

        // Se cuentan 175 cuando deberían ser 180: faltan 5.
        $response = $this->withHeaders($this->headers())
            ->putJson("/api/v1/cash/session/{$session->id}/close", ['closing_amount' => 175])
            ->assertOk();

        $this->assertFalse($response->json('data.is_open'));
        $this->assertSame(180.0, $response->json('data.expected_amount'));
        $this->assertSame(-5.0, $response->json('data.difference'));
        $this->assertSame('Faltante', $response->json('data.difference_label'));
    }

    public function test_un_turno_cerrado_no_se_cierra_otra_vez(): void
    {
        $session = $this->openSession();

        Sanctum::actingAs($this->cajero);

        $this->withHeaders($this->headers())
            ->putJson("/api/v1/cash/session/{$session->id}/close", ['closing_amount' => 100])
            ->assertOk();

        $this->withHeaders($this->headers())
            ->putJson("/api/v1/cash/session/{$session->id}/close", ['closing_amount' => 100])
            ->assertStatus(422)
            ->assertJsonPath('error', 'session_closed');
    }

    public function test_no_se_cierra_el_turno_de_otra_barberia(): void
    {
        $otra = $this->companyWithPlan(planAttributes: ['features' => self::FEATURES]);
        Branch::factory()->create(['company_id' => $otra->id]);
        $ajena = $this->openSession($otra);

        Sanctum::actingAs($this->cajero);

        $this->withHeaders($this->headers())
            ->putJson("/api/v1/cash/session/{$ajena->id}/close", ['closing_amount' => 100])
            ->assertNotFound();
    }

    /* ---------------------------------------------------------------------
     | Cobro
     |--------------------------------------------------------------------- */

    public function test_el_precio_lo_pone_el_catalogo_no_el_telefono(): void
    {
        $this->openSession();
        $service = $this->service(100);

        Sanctum::actingAs($this->cajero);

        // Aunque el móvil mande otro precio, manda el del catálogo.
        $response = $this->withHeaders($this->headers())
            ->postJson('/api/v1/sales', [
                'items' => [[
                    'type' => 'servicio',
                    'id' => $service->id,
                    'quantity' => 1,
                    'unit_price' => 1,
                ]],
                'payments' => [['payment_method' => 'efectivo', 'amount' => 100]],
            ])
            ->assertCreated();

        $this->assertSame(100.0, $response->json('data.total'));
    }

    public function test_los_pagos_tienen_que_cuadrar(): void
    {
        $this->openSession();
        $service = $this->service(100);

        Sanctum::actingAs($this->cajero);

        $this->withHeaders($this->headers())
            ->postJson('/api/v1/sales', $this->salePayload($service, 60))
            ->assertStatus(422)
            ->assertJsonValidationErrors('payments');
    }

    public function test_cobrar_una_cita_la_marca_atendida(): void
    {
        $this->openSession();
        $service = $this->service(80);

        $client = $this->inCompany($this->company, fn () => Client::factory()->create([
            'company_id' => $this->company->id,
        ]));

        $appointment = $this->inCompany($this->company, fn () => Appointment::create([
            'company_id' => $this->company->id,
            'personal_id' => $this->barbero->id,
            'client_id' => $client->id,
            'starts_at' => Carbon::today()->setTime(10, 0),
            'ends_at' => Carbon::today()->setTime(10, 30),
            'status' => 'confirmada',
        ]));

        Sanctum::actingAs($this->cajero);

        $this->withHeaders($this->headers())
            ->postJson('/api/v1/sales', [
                'appointment_id' => $appointment->id,
                'client_id' => $client->id,
                'personal_id' => $this->barbero->id,
                'items' => [['type' => 'servicio', 'id' => $service->id, 'quantity' => 1]],
                'payments' => [['payment_method' => 'efectivo', 'amount' => 80]],
            ])
            ->assertCreated();

        $this->assertSame('atendida', $appointment->fresh()->status);
    }

    public function test_un_servicio_de_otra_barberia_no_se_cobra(): void
    {
        $this->openSession();

        $otra = $this->companyWithPlan(planAttributes: ['features' => self::FEATURES]);
        $ajeno = $this->service(100, $otra);

        Sanctum::actingAs($this->cajero);

        $this->withHeaders($this->headers())
            ->postJson('/api/v1/sales', $this->salePayload($ajeno, 100))
            ->assertStatus(422)
            ->assertJsonValidationErrors('items');
    }

    public function test_sin_permiso_de_cobro_no_se_cobra(): void
    {
        $this->openSession();
        $service = $this->service(100);

        $barbero = $this->userInCompany($this->company, ['sales.view'], 'solo_mira_ventas');

        Sanctum::actingAs($barbero);

        $this->withHeaders($this->headers())
            ->postJson('/api/v1/sales', $this->salePayload($service, 100))
            ->assertForbidden()
            ->assertJsonPath('error', 'forbidden');
    }

    public function test_sin_el_modulo_pos_no_se_cobra(): void
    {
        $sinPos = $this->companyWithPlan(planAttributes: ['features' => ['caja']]);
        $user = $this->userInCompany($sinPos, ['sales.create'], 'sin_pos');

        Sanctum::actingAs($user);

        $this->withHeaders(['X-Company-Id' => (string) $sinPos->id])
            ->postJson('/api/v1/sales', ['items' => [], 'payments' => []])
            ->assertForbidden()
            ->assertJsonPath('error', 'plan_required');
    }

    public function test_el_importe_redondo_llega_como_decimal(): void
    {
        $this->openSession();
        $service = $this->service(100);

        Sanctum::actingAs($this->cajero);

        $response = $this->withHeaders($this->headers())
            ->postJson('/api/v1/sales', $this->salePayload($service, 100))
            ->assertCreated();

        // Igual que el resto de la API: 100.0 y no 100, para que el «as double»
        // de Dart no reviente con las cifras redondas.
        $this->assertStringContainsString('"total":100.0', $response->getContent());
    }

    /* ---------------------------------------------------------------------
     | Utilidades
     |--------------------------------------------------------------------- */

    protected function headers(array $extra = []): array
    {
        return array_merge(['X-Company-Id' => (string) $this->company->id], $extra);
    }

    protected function salePayload(Service $service, float $amount): array
    {
        return [
            'items' => [['type' => 'servicio', 'id' => $service->id, 'quantity' => 1]],
            'payments' => [['payment_method' => 'efectivo', 'amount' => $amount]],
        ];
    }

    protected function openSession(?Company $company = null, float $openingAmount = 100): CashSession
    {
        $company ??= $this->company;

        return $this->inCompany($company, function () use ($company, $openingAmount) {
            $caja = $company->is($this->company)
                ? $this->caja
                : Caja::create([
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
                'opening_amount' => $openingAmount,
            ]);
        });
    }

    protected function service(float $price, ?Company $company = null): Service
    {
        $company ??= $this->company;

        return $this->inCompany($company, fn () => Service::factory()->create([
            'company_id' => $company->id,
            'price' => $price,
            'duration_minutes' => 30,
        ]));
    }

    protected function product(float $salePrice, float $stock): Product
    {
        return $this->inCompany($this->company, function () use ($salePrice, $stock) {
            $product = Product::create([
                'company_id' => $this->company->id,
                'name' => 'Producto '.fake()->unique()->numerify('####'),
                'unit' => 'unidad',
                'cost_price' => $salePrice / 2,
                'sale_price' => $salePrice,
                'min_stock' => 0,
                'is_sellable' => true,
                'track_stock' => true,
                'active' => true,
            ]);

            $warehouse = Warehouse::allCompanies()
                ->where('company_id', $this->company->id)
                ->firstOrFail();

            app(StockManager::class)->receive($product, $warehouse, $stock);

            return $product;
        });
    }
}
