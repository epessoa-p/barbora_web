<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cada sucursal crea y mantiene su almacén espejo, que no se puede editar
 * desde el CRUD de almacenes.
 */
class WarehouseTest extends TestCase
{
    use RefreshDatabase;

    public function test_crear_una_sucursal_crea_su_almacen(): void
    {
        $company = $this->companyWithPlan();

        $branch = Branch::factory()->create([
            'company_id' => $company->id,
            'name' => 'Sucursal Centro',
            'phone' => '+591 3 111-2222',
            'address' => 'Av. Ballivián 100',
        ]);

        $warehouse = Warehouse::allCompanies()->where('branch_id', $branch->id)->first();

        $this->assertNotNull($warehouse);
        $this->assertSame('Sucursal Centro', $warehouse->name);
        $this->assertSame('+591 3 111-2222', $warehouse->phone);
        $this->assertSame('Av. Ballivián 100', $warehouse->address);
        $this->assertSame($company->id, $warehouse->company_id);
        $this->assertSame('ALM-0001', $warehouse->code);
    }

    public function test_el_codigo_es_correlativo_por_empresa(): void
    {
        $a = $this->companyWithPlan();
        $b = $this->companyWithPlan();

        Branch::factory()->count(2)->create(['company_id' => $a->id]);
        Branch::factory()->create(['company_id' => $b->id]);

        $this->assertSame(
            ['ALM-0001', 'ALM-0002'],
            Warehouse::allCompanies()->where('company_id', $a->id)->orderBy('code')->pluck('code')->all()
        );

        // Cada empresa lleva su propia numeración.
        $this->assertSame(
            ['ALM-0001'],
            Warehouse::allCompanies()->where('company_id', $b->id)->pluck('code')->all()
        );
    }

    public function test_el_almacen_sigue_los_cambios_de_su_sucursal(): void
    {
        $company = $this->companyWithPlan();
        $branch = Branch::factory()->create(['company_id' => $company->id, 'name' => 'Centro']);

        $branch->update(['name' => 'Centro Renovado', 'address' => 'Nueva dirección 456']);

        $warehouse = Warehouse::allCompanies()->where('branch_id', $branch->id)->first();

        $this->assertSame('Centro Renovado', $warehouse->name);
        $this->assertSame('Nueva dirección 456', $warehouse->address);
        // El código lo genera el sistema una sola vez y no cambia.
        $this->assertSame('ALM-0001', $warehouse->code);
    }

    public function test_borrar_la_sucursal_borra_su_almacen(): void
    {
        $company = $this->companyWithPlan();
        $branch = Branch::factory()->create(['company_id' => $company->id]);

        $branch->delete();

        $this->assertSame(0, Warehouse::allCompanies()->where('branch_id', $branch->id)->count());
        $this->assertSame(1, Warehouse::allCompanies()->withTrashed()->where('branch_id', $branch->id)->count());
    }

    public function test_el_almacen_de_una_sucursal_no_se_edita_desde_su_crud(): void
    {
        $company = $this->companyWithPlan();
        $branch = Branch::factory()->create(['company_id' => $company->id]);
        $warehouse = Warehouse::allCompanies()->where('branch_id', $branch->id)->first();

        $user = $this->userInCompany($company, ['warehouses.view', 'warehouses.edit', 'warehouses.delete']);

        $this->actingInCompany($user, $company)
            ->get(route('warehouses.edit', $warehouse))
            ->assertRedirect(route('warehouses.index'));

        $this->actingInCompany($user, $company)
            ->put(route('warehouses.update', $warehouse), ['name' => 'Intento de cambio'])
            ->assertSessionHasErrors('error');

        $this->actingInCompany($user, $company)
            ->delete(route('warehouses.destroy', $warehouse))
            ->assertSessionHasErrors('error');

        $this->assertSame($branch->name, $warehouse->refresh()->name);
        $this->assertNull($warehouse->deleted_at);
    }

    public function test_un_almacen_independiente_si_se_edita(): void
    {
        $company = $this->companyWithPlan();
        $user = $this->userInCompany($company, ['warehouses.view', 'warehouses.create', 'warehouses.edit']);

        $this->actingInCompany($user, $company)
            ->post(route('warehouses.store'), ['name' => 'Depósito Central', 'active' => 1])
            ->assertRedirect(route('warehouses.index'));

        $warehouse = Warehouse::allCompanies()->where('name', 'Depósito Central')->firstOrFail();

        $this->assertNull($warehouse->branch_id);
        $this->assertSame('ALM-0001', $warehouse->code);

        $this->actingInCompany($user, $company)->get(route('warehouses.edit', $warehouse))->assertOk();

        $this->actingInCompany($user, $company)
            ->put(route('warehouses.update', $warehouse), ['name' => 'Depósito Norte', 'active' => 1])
            ->assertRedirect(route('warehouses.index'));

        $this->assertSame('Depósito Norte', $warehouse->refresh()->name);
    }

    public function test_los_almacenes_requieren_el_modulo_inventario(): void
    {
        $sinInventario = $this->companyWithPlan(planAttributes: ['features' => ['agenda']]);
        $user = $this->userInCompany($sinInventario, ['warehouses.view'], 'con-almacenes');

        $this->actingInCompany($user, $sinInventario)
            ->get(route('warehouses.index'))
            ->assertForbidden();
    }

    public function test_no_se_ven_los_almacenes_de_otra_empresa(): void
    {
        $mia = $this->companyWithPlan();
        $ajena = $this->companyWithPlan();

        Branch::factory()->create(['company_id' => $mia->id, 'name' => 'Mi Sucursal']);
        Branch::factory()->create(['company_id' => $ajena->id, 'name' => 'Sucursal Ajena']);

        $user = $this->userInCompany($mia, ['warehouses.view']);

        $this->actingInCompany($user, $mia)
            ->get(route('warehouses.index'))
            ->assertOk()
            ->assertSee('Mi Sucursal')
            ->assertDontSee('Sucursal Ajena');
    }
}
