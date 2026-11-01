<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Caja;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\Client;
use App\Models\Company;
use App\Models\Personal;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Support\BranchNumbering;
use App\Support\SaleRegistrar;
use App\Support\StockManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Códigos correlativos por sucursal (V-, C-, T-, I-) y las relaciones que
 * los sostienen: el personal, la caja y el almacén son de una sucursal.
 */
class BranchNumberingTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected Branch $centro;
    protected Branch $norte;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = $this->companyWithPlan(planAttributes: ['features' => ['caja', 'pos', 'inventario', 'agenda']]);
        $this->centro = Branch::factory()->create(['company_id' => $this->company->id, 'name' => 'CENTRO', 'code' => null]);
        $this->norte = Branch::factory()->create(['company_id' => $this->company->id, 'name' => 'NORTE', 'code' => null]);
    }

    public function test_las_sucursales_reciben_codigo_por_empresa(): void
    {
        $this->assertSame('S01', $this->centro->code);
        $this->assertSame('S02', $this->norte->code);

        // Otra empresa también empieza en S01.
        $otra = $this->companyWithPlan();
        $this->assertSame('S01', Branch::factory()->create(['company_id' => $otra->id, 'code' => null])->code);
    }

    public function test_cada_sucursal_numera_sus_ventas_desde_uno(): void
    {
        $this->openSession($this->centro);
        $this->openSession($this->norte);

        $numbers = [
            $this->sell($this->centro)->number,
            $this->sell($this->norte)->number,
            $this->sell($this->centro)->number,
        ];

        $this->assertSame(['V-000001', 'V-000001', 'V-000002'], $numbers);
    }

    public function test_la_venta_cobra_solo_en_una_caja_de_su_sucursal(): void
    {
        $this->openSession($this->centro);

        try {
            $this->sell($this->norte);
            $this->fail('Cobró en la caja de otra sucursal.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('NORTE', $e->getMessage());
        }

        // Sin sucursal indicada, la toma del turno abierto.
        $sale = $this->sell(null);
        $this->assertSame($this->centro->id, $sale->branch_id);
    }

    public function test_la_venta_del_barbero_es_de_su_sucursal(): void
    {
        $this->openSession($this->centro);
        $session = $this->openSession($this->norte);
        $barber = $this->barber($this->norte);

        $sale = $this->sell(null, ['personal_id' => $barber->id]);

        $this->assertSame($this->norte->id, $sale->branch_id);
        $this->assertSame($session->id, $sale->cash_session_id);
    }

    public function test_citas_y_turnos_numeran_por_sucursal(): void
    {
        $centro = $this->barber($this->centro);
        $norte = $this->barber($this->norte);

        $this->assertSame('C-000001', $this->appointment($centro)->number);
        $this->assertSame('C-000002', $this->appointment($centro)->number);
        $this->assertSame('C-000001', $this->appointment($norte)->number);
        $this->assertSame($this->norte->id, $this->appointment($norte)->branch_id, 'La cita va a la sucursal del barbero.');

        $turno = $this->openSession($this->norte);
        $this->assertSame('T-000001', $turno->number);
        $this->assertSame($this->norte->id, $turno->branch_id, 'El turno es de la sucursal de su caja.');
    }

    public function test_los_movimientos_de_inventario_numeran_por_almacen_de_sucursal(): void
    {
        $product = $this->inCompany($this->company, fn () => Product::create([
            'company_id' => $this->company->id, 'name' => 'CERA', 'sale_price' => 10, 'track_stock' => true,
        ]));
        $stock = app(StockManager::class);

        $moves = $this->inCompany($this->company, fn () => [
            $stock->receive($product, $this->warehouseOf($this->centro), 5, ['reason' => 'compra']),
            $stock->receive($product, $this->warehouseOf($this->norte), 5, ['reason' => 'compra']),
            $stock->issue($product, $this->warehouseOf($this->centro), 1, ['reason' => 'consumo']),
        ]);

        $this->assertSame(['I-000001', 'I-000001', 'I-000002'], array_column($moves, 'number'));
        $this->assertSame($this->norte->id, $moves[1]->branch_id);
    }

    public function test_el_personal_y_la_caja_toman_la_sucursal_por_defecto(): void
    {
        $personal = $this->inCompany($this->company, fn () => Personal::create([
            'company_id' => $this->company->id, 'full_name' => 'SIN SUCURSAL', 'active' => true,
        ]));
        $this->assertSame($this->centro->id, $personal->branch_id);

        $barber = $this->barber($this->norte);
        $caja = $this->inCompany($this->company, fn () => Caja::create([
            'company_id' => $this->company->id, 'name' => 'CAJA NORTE', 'personal_id' => $barber->id,
        ]));
        $this->assertSame($this->norte->id, $caja->branch_id, 'La caja va a la sucursal de su responsable.');
    }

    public function test_la_caja_no_se_asigna_a_personal_de_otra_sucursal(): void
    {
        $barber = $this->barber($this->norte);
        $user = $this->userInCompany($this->company, ['cajas.create'], 'cajas_admin');

        $this->actingInCompany($user, $this->company)
            ->post(route('cajas.store'), [
                'name' => 'CAJA',
                'branch_id' => $this->centro->id,
                'personal_id' => $barber->id,
            ])
            ->assertSessionHasErrors('personal_id');

        $this->actingInCompany($user, $this->company)
            ->post(route('cajas.store'), ['name' => 'CAJA'])
            ->assertSessionHasErrors('branch_id');
    }

    public function test_el_personal_exige_sucursal(): void
    {
        $cargo = $this->inCompany($this->company, fn () => \App\Models\Cargo::create([
            'company_id' => $this->company->id, 'name' => 'BARBERO', 'active' => true,
        ]));
        $user = $this->userInCompany($this->company, ['personal.create'], 'rrhh');

        $this->actingInCompany($user, $this->company)
            ->post(route('personal.store'), ['cargo_id' => $cargo->id, 'full_name' => 'ANA'])
            ->assertSessionHasErrors('branch_id');

        $this->actingInCompany($user, $this->company)
            ->post(route('personal.store'), ['cargo_id' => $cargo->id, 'full_name' => 'ANA', 'branch_id' => $this->norte->id])
            ->assertSessionHasNoErrors();

        $this->assertSame($this->norte->id, $this->inCompany($this->company, fn () => Personal::where('full_name', 'ANA')->value('branch_id')));
    }

    public function test_el_codigo_de_sucursal_se_puede_repetir_entre_empresas(): void
    {
        $otra = $this->companyWithPlan();
        $user = $this->userInCompany($otra, ['branches.create'], 'sucursales');

        $this->actingInCompany($user, $otra)
            ->post(route('branches.store'), ['name' => 'CENTRAL', 'code' => 'S01'])
            ->assertSessionHasNoErrors();

        $this->assertTrue(Branch::allCompanies()->where('company_id', $otra->id)->where('code', 'S01')->exists());
    }

    public function test_el_relleno_renumera_por_sucursal_y_actualiza_las_referencias(): void
    {
        // Como estaba antes: correlativo de empresa y sin sucursal en la venta.
        $cajaNorte = $this->inCompany($this->company, fn () => Caja::create([
            'company_id' => $this->company->id, 'name' => 'NORTE', 'branch_id' => $this->norte->id,
        ]));
        $session = $this->inCompany($this->company, fn () => CashSession::create([
            'company_id' => $this->company->id, 'caja_id' => $cajaNorte->id,
            'status' => 'abierta', 'opened_at' => now(), 'opening_amount' => 0,
        ]));

        foreach (['V-000001' => $this->centro->id, 'V-000002' => null] as $number => $branch) {
            DB::table('sales')->insert([
                'company_id' => $this->company->id, 'branch_id' => $branch,
                'cash_session_id' => $branch ? null : $session->id,
                'number' => $number, 'status' => 'pagada', 'sold_at' => now(),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        DB::table('cash_movements')->insert([
            'company_id' => $this->company->id, 'cash_session_id' => $session->id, 'type' => 'ingreso',
            'payment_method' => 'efectivo', 'concept' => 'Venta V-000002', 'amount' => 10,
            'reference' => 'V-000002', 'created_at' => now(), 'updated_at' => now(),
        ]);

        BranchNumbering::backfill();

        // La segunda era del norte (por su turno): pasa a ser la V-000001 de allí.
        $this->assertSame(
            [[$this->centro->id, 'V-000001'], [$this->norte->id, 'V-000001']],
            Sale::allCompanies()->orderBy('id')->get()->map(fn ($s) => [$s->branch_id, $s->number])->all()
        );

        $movement = CashMovement::allCompanies()->first();
        $this->assertSame('V-000001', $movement->reference);
        $this->assertSame('Venta V-000001', $movement->concept);

        // El contador sigue donde quedó.
        $this->openSession($this->norte);
        $this->assertSame('V-000002', $this->sell($this->norte)->number);
    }

    // ── Ayudas ─────────────────────────────────────────────────────────────

    protected function barber(Branch $branch): Personal
    {
        return $this->inCompany($this->company, fn () => Personal::create([
            'company_id' => $this->company->id, 'branch_id' => $branch->id,
            'full_name' => 'BARBERO '.fake()->unique()->numerify('###'), 'bookable' => true, 'active' => true,
        ]));
    }

    protected function appointment(Personal $barber): Appointment
    {
        $client = $this->inCompany($this->company, fn () => Client::create([
            'company_id' => $this->company->id, 'full_name' => 'CLIENTE',
        ]));

        return $this->inCompany($this->company, fn () => Appointment::create([
            'company_id' => $this->company->id, 'personal_id' => $barber->id, 'client_id' => $client->id,
            'starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addMinutes(30), 'status' => 'reservada',
        ]));
    }

    protected function openSession(Branch $branch): CashSession
    {
        return $this->inCompany($this->company, function () use ($branch) {
            $caja = Caja::where('branch_id', $branch->id)->first()
                ?? Caja::create(['company_id' => $this->company->id, 'name' => 'CAJA '.$branch->name, 'branch_id' => $branch->id]);

            return CashSession::create([
                'company_id' => $this->company->id, 'caja_id' => $caja->id,
                'status' => 'abierta', 'opened_at' => now(), 'opening_amount' => 0,
            ]);
        });
    }

    protected function sell(?Branch $branch, array $data = []): Sale
    {
        return $this->inCompany($this->company, fn () => app(SaleRegistrar::class)->register(
            $this->company,
            $data + ['branch_id' => $branch?->id],
            [['type' => 'servicio', 'description' => 'CORTE', 'quantity' => 1, 'unit_price' => 50]],
            [['payment_method' => 'efectivo', 'amount' => 50]],
        ));
    }

    protected function warehouseOf(Branch $branch): Warehouse
    {
        return Warehouse::allCompanies()->where('branch_id', $branch->id)->firstOrFail();
    }
}
