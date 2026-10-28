<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Caja;
use App\Models\CashSession;
use App\Models\Client;
use App\Models\Company;
use App\Models\Personal;
use App\Models\Product;
use App\Models\Sale;
use App\Support\ShopTime;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * El Inicio de la app: solo con dashboard.view, y cada bloque según su módulo
 * y su permiso.
 */
class ApiDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = $this->companyWithPlan(planAttributes: [
            'features' => ['agenda', 'clientes', 'pos', 'caja', 'inventario'],
        ]);
    }

    protected function headers(): array
    {
        return ['X-Company-Id' => (string) $this->company->id];
    }

    public function test_sin_el_permiso_no_hay_inicio(): void
    {
        $barbero = $this->userInCompany($this->company, ['appointments.view'], 'barbero_dash');

        Sanctum::actingAs($barbero);

        $this->getJson('/api/v1/dashboard', $this->headers())->assertForbidden();
    }

    public function test_el_jefe_ve_el_resumen_del_dia(): void
    {
        $now = ShopTime::now($this->company);
        $today = $now->copy()->startOfDay();

        $this->inCompany($this->company, function () use ($now, $today) {
            $barber = Personal::create([
                'company_id' => $this->company->id, 'full_name' => 'TANIA', 'active' => true, 'bookable' => true,
            ]);
            $client = Client::factory()->create(['company_id' => $this->company->id]);

            $cita = fn (Carbon $at, string $status) => Appointment::create([
                'company_id' => $this->company->id,
                'personal_id' => $barber->id,
                'client_id' => $client->id,
                'starts_at' => $at,
                'ends_at' => $at->copy()->addMinutes(30),
                'status' => $status,
            ]);

            $cita($today->copy()->setTime(0, 5), 'atendida');
            // Una cita que empieza ahora (en la hora de la barbería): sigue siendo «próxima».
            $cita($now->copy()->second(0), 'reservada');

            $sale = fn (Carbon $at, float $total) => Sale::create([
                'company_id' => $this->company->id,
                'number' => 'V-'.fake()->unique()->numerify('####'),
                'status' => 'pagada',
                'subtotal' => $total, 'discount' => 0, 'tip' => 0, 'total' => $total,
                'sold_at' => $at,
            ]);

            $sale($today->copy()->setTime(0, 10), 100);
            $sale($today->copy()->setTime(0, 20), 50);
            $sale($today->copy()->subDay()->setTime(12, 0), 80);

            $caja = Caja::create(['company_id' => $this->company->id, 'name' => 'Caja principal', 'balance' => 0, 'active' => true]);
            CashSession::create([
                'company_id' => $this->company->id, 'caja_id' => $caja->id,
                'status' => 'abierta', 'opened_at' => now(), 'opening_amount' => 100,
            ]);

            Product::create([
                'company_id' => $this->company->id, 'name' => 'CERA', 'unit' => 'unidad',
                'cost_price' => 10, 'sale_price' => 20, 'min_stock' => 3,
                'is_sellable' => true, 'track_stock' => true, 'active' => true,
            ]);
        });

        $jefe = $this->userInCompany($this->company, [
            'dashboard.view', 'appointments.view', 'sales.view', 'cash.view', 'products.view',
        ], 'jefe_dash');

        Sanctum::actingAs($jefe);

        $response = $this->getJson('/api/v1/dashboard', $this->headers())->assertOk();

        $this->assertSame($today->toDateString(), $response->json('date'));

        $this->assertSame(2, $response->json('agenda.total'));
        $this->assertSame(1, $response->json('agenda.attended'));
        $this->assertCount(1, $response->json('agenda.next'), 'La próxima cita es la que viene, no la ya atendida.');

        $this->assertSame(150.0, $response->json('sales.today'));
        $this->assertSame(2, $response->json('sales.count'));
        $this->assertSame(75.0, $response->json('sales.average'));
        $this->assertSame(80.0, $response->json('sales.yesterday'));
        $this->assertCount(7, $response->json('sales.week.data'));
        $this->assertSame(150.0, $response->json('sales.week.data.6'));

        $this->assertTrue($response->json('cash.open'));
        $this->assertSame('Caja principal', $response->json('cash.caja'));

        $this->assertSame(1, $response->json('stock.low_count'));
        $this->assertSame('CERA', $response->json('stock.items.0.name'));
    }

    public function test_cada_bloque_exige_su_permiso(): void
    {
        $soloAgenda = $this->userInCompany($this->company, ['dashboard.view', 'appointments.view'], 'encargado_dash');

        Sanctum::actingAs($soloAgenda);

        $response = $this->getJson('/api/v1/dashboard', $this->headers())->assertOk();

        $this->assertNotNull($response->json('agenda'));
        $this->assertNull($response->json('sales'), 'Sin sales.view no se ve lo cobrado.');
        $this->assertNull($response->json('cash'));
        $this->assertNull($response->json('stock'));
    }

    public function test_cada_bloque_exige_su_modulo_del_plan(): void
    {
        $basico = $this->companyWithPlan(planAttributes: ['features' => ['agenda']]);
        $jefe = $this->userInCompany($basico, ['dashboard.view', 'appointments.view', 'sales.view'], 'jefe_basico');

        Sanctum::actingAs($jefe);

        $response = $this->getJson('/api/v1/dashboard', ['X-Company-Id' => (string) $basico->id])->assertOk();

        $this->assertNotNull($response->json('agenda'));
        $this->assertNull($response->json('sales'), 'Sin el módulo pos no hay bloque de ventas.');
    }
}
