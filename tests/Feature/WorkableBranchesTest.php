<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Caja;
use App\Models\Company;
use App\Models\Personal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Cada usuario ve, al vender, agendar o abrir caja, solo las sucursales en las
 * que trabaja: su base y las adicionales. El administrador ve todas. Con una
 * sola, el selector no se muestra.
 */
class WorkableBranchesTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected Branch $norte;
    protected Branch $sur;
    protected Branch $centro;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = $this->companyWithPlan();
        $this->norte = Branch::factory()->create(['company_id' => $this->company->id, 'name' => 'NORTE']);
        $this->sur = Branch::factory()->create(['company_id' => $this->company->id, 'name' => 'SUR']);
        $this->centro = Branch::factory()->create(['company_id' => $this->company->id, 'name' => 'CENTRO']);
    }

    protected function staff(array $permissions, Branch $base, array $extra = [], string $role = 'cajero'): User
    {
        $user = $this->userInCompany($this->company, $permissions, $role);
        $personal = Personal::withoutGlobalScopes()->create([
            'company_id' => $this->company->id, 'user_id' => $user->id, 'full_name' => strtoupper($role),
            'branch_id' => $base->id, 'active' => true,
        ]);
        $personal->syncExtraBranches(array_map(fn (Branch $b) => $b->id, $extra));

        return $user;
    }

    public function test_con_una_sola_sucursal_asignada_no_hay_selector(): void
    {
        $user = $this->staff(['sales.view', 'sales.create'], $this->sur);

        $this->assertSame([$this->sur->id], Branch::workableIdsFor($user, $this->company->id));

        $this->actingInCompany($user, $this->company)->get(route('sales.create'))
            ->assertOk()
            ->assertDontSee('<select id="branch_id"', false);
    }

    public function test_un_gerente_con_dos_sucursales_elige_entre_esas_dos(): void
    {
        $user = $this->staff(['sales.view', 'sales.create'], $this->norte, [$this->sur], 'gerente');

        $html = $this->actingInCompany($user, $this->company)->get(route('sales.create'))->assertOk()->getContent();

        $this->assertStringContainsString('<select id="branch_id"', $html);
        $this->assertStringContainsString('NORTE', $html);
        $this->assertStringContainsString('SUR', $html);
        $this->assertStringNotContainsString('>
                    CENTRO', $html);
        $this->assertEqualsCanonicalizing([$this->norte->id, $this->sur->id], Branch::workableIdsFor($user, $this->company->id));
    }

    public function test_el_administrador_ve_todas(): void
    {
        $admin = $this->staff(['branches.edit', 'sales.create'], $this->norte, [], 'admin');

        $this->assertNull(Branch::workableIdsFor($admin, $this->company->id));
    }

    public function test_las_cajas_de_la_app_son_las_de_sus_sucursales(): void
    {
        Caja::create(['company_id' => $this->company->id, 'branch_id' => $this->norte->id, 'name' => 'CAJA NORTE', 'balance' => 0, 'active' => true]);
        Caja::create(['company_id' => $this->company->id, 'branch_id' => $this->sur->id, 'name' => 'CAJA SUR', 'balance' => 0, 'active' => true]);

        $user = $this->staff(['cash.view'], $this->sur);
        Sanctum::actingAs($user);

        $names = collect($this->getJson('/api/v1/cash/cajas', ['X-Company-Id' => (string) $this->company->id])->assertOk()->json('data'))
            ->pluck('name');

        $this->assertSame(['CAJA SUR'], $names->all());
    }

    public function test_las_sucursales_adicionales_se_asignan_desde_la_app(): void
    {
        $admin = $this->userInCompany($this->company, ['personal.view', 'personal.edit'], 'rrhh');
        $gerente = Personal::withoutGlobalScopes()->create([
            'company_id' => $this->company->id, 'full_name' => 'GERENTE', 'branch_id' => $this->norte->id, 'active' => true,
            'cargo_id' => \App\Models\Cargo::create([
                'company_id' => $this->company->id, 'name' => 'GERENTE', 'active' => true,
                'role_id' => \App\Models\Role::factory()->ownedBy($this->company)->create(['name' => 'Gerente'])->id,
            ])->id,
        ]);
        Sanctum::actingAs($admin);

        $this->putJson("/api/v1/personal/{$gerente->id}", [
            'full_name' => 'GERENTE', 'cargo_id' => $gerente->cargo_id, 'branch_id' => $this->norte->id,
            // La base repetida como adicional se ignora.
            'extra_branch_ids' => [$this->sur->id, $this->centro->id, $this->norte->id],
        ], ['X-Company-Id' => (string) $this->company->id])
            ->assertOk()
            ->assertJsonPath('data.extra_branch_ids', fn ($ids) => collect($ids)->sort()->values()->all() === collect([$this->sur->id, $this->centro->id])->sort()->values()->all());

        $this->assertCount(3, $gerente->refresh()->branchIds());
    }
}
