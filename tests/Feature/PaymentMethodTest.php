<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Caja;
use App\Models\CashSession;
use App\Models\Company;
use App\Models\PaymentMethod;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Métodos de pago configurables por barbería.
 *
 * Lo que hay que proteger aquí es el arqueo. Antes solo existía «efectivo» como
 * valor fijo; ahora cada barbería puede añadir Tigo Money, el QR de su banco o
 * lo que use. Si uno de esos entrara al cajón por error, el turno cuadraría mal
 * todos los días y nadie sabría por qué.
 */
class PaymentMethodTest extends TestCase
{
    use RefreshDatabase;

    protected const FEATURES = ['caja', 'pos', 'agenda'];

    protected Company $company;
    protected Caja $caja;
    protected User $admin;

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

        $this->admin = $this->userInCompany($this->company, [
            'settings.view', 'settings.edit',
            'cash.view', 'cash.create', 'cash.edit',
            'sales.view', 'sales.create',
        ], 'admin_config');
    }

    /* ---------------------------------------------------------------------
     | El arqueo: lo que de verdad importa
     |--------------------------------------------------------------------- */

    public function test_un_metodo_que_no_es_efectivo_no_entra_en_el_arqueo(): void
    {
        $session = $this->openSession(openingAmount: 100);

        $this->inCompany($this->company, fn () => PaymentMethod::create([
            'company_id' => $this->company->id,
            'name' => 'Tigo Money',
            'slug' => 'tigo_money',
            'counts_as_cash' => false,
            'active' => true,
        ]));

        $service = $this->service(200);
        $this->sell($service, 200, 'tigo_money');

        $totals = $this->inCompany($this->company, fn () => $session->fresh()->totals());

        // Entraron 200, pero al cajón no llegó nada: está en la billetera.
        $this->assertSame(200.0, $totals['income']);
        $this->assertSame(0.0, $totals['cash_income']);
        $this->assertSame(100.0, $totals['expected_cash'], 'El cajón sigue con el fondo.');
    }

    public function test_un_metodo_marcado_como_efectivo_si_entra(): void
    {
        $session = $this->openSession(openingAmount: 100);

        $this->inCompany($this->company, fn () => PaymentMethod::create([
            'company_id' => $this->company->id,
            'name' => 'Efectivo caja chica',
            'slug' => 'efectivo_chica',
            'counts_as_cash' => true,
            'active' => true,
        ]));

        $this->sell($this->service(80), 80, 'efectivo_chica');

        $totals = $this->inCompany($this->company, fn () => $session->fresh()->totals());

        $this->assertSame(80.0, $totals['cash_income']);
        $this->assertSame(180.0, $totals['expected_cash']);
    }

    public function test_el_desglose_del_turno_incluye_los_metodos_nuevos(): void
    {
        $session = $this->openSession();

        $this->inCompany($this->company, fn () => PaymentMethod::create([
            'company_id' => $this->company->id,
            'name' => 'Tigo Money',
            'slug' => 'tigo_money',
            'counts_as_cash' => false,
            'active' => true,
        ]));

        $this->sell($this->service(150), 150, 'tigo_money');

        $breakdown = $this->inCompany($this->company, fn () => $session->fresh()->byPaymentMethod());

        $this->assertArrayHasKey('tigo_money', $breakdown);
        $this->assertSame(150.0, $breakdown['tigo_money']['income']);
    }

    /* ---------------------------------------------------------------------
     | Altas por defecto
     |--------------------------------------------------------------------- */

    public function test_una_barberia_nueva_nace_con_sus_metodos(): void
    {
        $nueva = Company::factory()->create();

        $methods = $this->inCompany($nueva, fn () => PaymentMethod::ordered()->get());

        $this->assertCount(4, $methods);
        $this->assertSame(['efectivo', 'tarjeta', 'transferencia', 'qr'], $methods->pluck('slug')->all());

        // Solo el efectivo cuenta en el cajón.
        $this->assertSame(['efectivo'], $methods->where('counts_as_cash', true)->pluck('slug')->all());
    }

    public function test_los_metodos_de_una_barberia_no_se_ven_en_otra(): void
    {
        $otra = $this->companyWithPlan(planAttributes: ['features' => self::FEATURES]);

        $this->inCompany($otra, fn () => PaymentMethod::create([
            'company_id' => $otra->id,
            'name' => 'Yape',
            'slug' => 'yape',
            'counts_as_cash' => false,
            'active' => true,
        ]));

        $mios = $this->inCompany($this->company, fn () => PaymentMethod::pluck('slug')->all());

        $this->assertNotContains('yape', $mios);
    }

    public function test_dos_barberias_pueden_tener_el_mismo_slug(): void
    {
        $otra = $this->companyWithPlan(planAttributes: ['features' => self::FEATURES]);

        // Las dos nacen con «efectivo» y no se pisan.
        $this->assertSame(1, $this->inCompany($this->company, fn () => PaymentMethod::where('slug', 'efectivo')->count()));
        $this->assertSame(1, $this->inCompany($otra, fn () => PaymentMethod::where('slug', 'efectivo')->count()));
    }

    /* ---------------------------------------------------------------------
     | CRUD
     |--------------------------------------------------------------------- */

    public function test_se_crea_un_metodo_nuevo(): void
    {
        $this->actingInCompany($this->admin, $this->company)
            ->post(route('payment-methods.store'), [
                'name' => 'Tigo Money',
                'requires_reference' => 1,
            ])
            ->assertRedirect(route('payment-methods.index'));

        $method = $this->inCompany($this->company, fn () => PaymentMethod::where('name', 'Tigo Money')->firstOrFail());

        $this->assertSame('tigo_money', $method->slug);
        $this->assertFalse($method->counts_as_cash, 'Por defecto no entra al cajón.');
        $this->assertTrue($method->requires_reference);
    }

    public function test_renombrar_no_cambia_el_slug(): void
    {
        $session = $this->openSession();
        $this->sell($this->service(50), 50, 'qr');

        $method = $this->inCompany($this->company, fn () => PaymentMethod::where('slug', 'qr')->firstOrFail());

        $this->actingInCompany($this->admin, $this->company)
            ->put(route('payment-methods.update', $method), [
                'name' => 'QR Banco Unión',
                'active' => 1,
            ])
            ->assertRedirect();

        $method->refresh();

        $this->assertSame('QR Banco Unión', $method->name);
        $this->assertSame('qr', $method->slug, 'Cambiar el slug desharía el historial.');

        // Y el turno sigue sabiendo de qué método habla.
        $breakdown = $this->inCompany($this->company, fn () => $session->fresh()->byPaymentMethod());
        $this->assertSame(50.0, $breakdown['qr']['income']);
    }

    public function test_un_metodo_ya_usado_se_da_de_baja_en_vez_de_borrarse(): void
    {
        $this->openSession();
        $this->sell($this->service(50), 50, 'tarjeta');

        $method = $this->inCompany($this->company, fn () => PaymentMethod::where('slug', 'tarjeta')->firstOrFail());

        $this->actingInCompany($this->admin, $this->company)
            ->delete(route('payment-methods.destroy', $method))
            ->assertRedirect();

        $method->refresh();

        $this->assertFalse($method->active);
        $this->assertNull($method->deleted_at, 'No se borra: el historial lo referencia.');
    }

    public function test_un_metodo_sin_uso_si_se_borra(): void
    {
        $method = $this->inCompany($this->company, fn () => PaymentMethod::where('slug', 'qr')->firstOrFail());

        $this->actingInCompany($this->admin, $this->company)
            ->delete(route('payment-methods.destroy', $method))
            ->assertRedirect();

        $this->assertSoftDeleted('payment_methods', ['id' => $method->id]);
    }

    public function test_no_se_puede_dejar_la_barberia_sin_efectivo(): void
    {
        $efectivo = $this->inCompany($this->company, fn () => PaymentMethod::where('slug', 'efectivo')->firstOrFail());

        // Quitarle la marca al único que la tiene dejaría el arqueo sin contra
        // qué comparar.
        $this->actingInCompany($this->admin, $this->company)
            ->put(route('payment-methods.update', $efectivo), ['name' => 'Efectivo', 'active' => 1])
            ->assertSessionHasErrors('counts_as_cash');

        $this->assertTrue($efectivo->fresh()->counts_as_cash);
    }

    public function test_tampoco_se_puede_borrar_el_ultimo_metodo_en_efectivo(): void
    {
        $efectivo = $this->inCompany($this->company, fn () => PaymentMethod::where('slug', 'efectivo')->firstOrFail());

        $this->actingInCompany($this->admin, $this->company)
            ->delete(route('payment-methods.destroy', $efectivo))
            ->assertSessionHasErrors('error');

        $this->assertNotNull($efectivo->fresh());
    }

    public function test_sin_permiso_no_se_configura(): void
    {
        $barbero = $this->userInCompany($this->company, ['settings.view'], 'solo_mira_config');

        $this->actingInCompany($barbero, $this->company)
            ->get(route('payment-methods.index'))->assertOk();

        $this->actingInCompany($barbero, $this->company)
            ->get(route('payment-methods.create'))->assertForbidden();
    }

    public function test_no_se_edita_el_metodo_de_otra_barberia(): void
    {
        $otra = $this->companyWithPlan(planAttributes: ['features' => self::FEATURES]);
        $ajeno = $this->inCompany($otra, fn () => PaymentMethod::where('slug', 'efectivo')->firstOrFail());

        $this->actingInCompany($this->admin, $this->company)
            ->get(route('payment-methods.edit', $ajeno))
            ->assertNotFound();
    }

    /* ---------------------------------------------------------------------
     | Cobro
     |--------------------------------------------------------------------- */

    public function test_se_cobra_con_un_metodo_nuevo(): void
    {
        $this->openSession();

        $this->inCompany($this->company, fn () => PaymentMethod::create([
            'company_id' => $this->company->id,
            'name' => 'Tigo Money',
            'slug' => 'tigo_money',
            'counts_as_cash' => false,
            'active' => true,
        ]));

        $this->sell($this->service(120), 120, 'tigo_money')->assertRedirect();

        $this->assertDatabaseHas('sale_payments', ['payment_method' => 'tigo_money']);
    }

    public function test_no_se_cobra_con_un_metodo_inventado(): void
    {
        $this->openSession();
        $service = $this->service(50);

        $seller = $this->userInCompany($this->company, ['sales.view', 'sales.create'], 'vendedor');

        $this->actingInCompany($seller, $this->company)
            ->post(route('sales.store'), [
                'items' => [['type' => 'servicio', 'id' => $service->id, 'quantity' => 1]],
                'payments' => [['payment_method' => 'bitcoin', 'amount' => 50]],
            ])
            ->assertSessionHasErrors('payments.0.payment_method');
    }

    /* ---------------------------------------------------------------------
     | Utilidades
     |--------------------------------------------------------------------- */

    protected function openSession(float $openingAmount = 100): CashSession
    {
        return $this->inCompany($this->company, fn () => CashSession::create([
            'company_id' => $this->company->id,
            'caja_id' => $this->caja->id,
            'status' => 'abierta',
            'opened_at' => now(),
            'opening_amount' => $openingAmount,
        ]));
    }

    protected function service(float $price): Service
    {
        return $this->inCompany($this->company, fn () => Service::factory()->create([
            'company_id' => $this->company->id,
            'price' => $price,
            'duration_minutes' => 30,
        ]));
    }

    protected function sell(Service $service, float $amount, string $method)
    {
        $seller = $this->userInCompany(
            $this->company,
            ['sales.view', 'sales.create'],
            'vendedor_'.fake()->unique()->numerify('####'),
        );

        return $this->actingInCompany($seller, $this->company)
            ->post(route('sales.store'), [
                'items' => [['type' => 'servicio', 'id' => $service->id, 'quantity' => 1]],
                'payments' => [['payment_method' => $method, 'amount' => $amount]],
            ]);
    }
}
