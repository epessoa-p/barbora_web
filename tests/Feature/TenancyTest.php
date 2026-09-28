<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenancyTest extends TestCase
{
    use RefreshDatabase;

    public function test_una_empresa_no_ve_los_datos_de_otra(): void
    {
        $tenancy = app(Tenancy::class);

        $a = Company::factory()->create();
        $b = Company::factory()->create();

        Branch::factory()->create(['company_id' => $a->id, 'name' => 'Sucursal de A']);
        Branch::factory()->create(['company_id' => $b->id, 'name' => 'Sucursal de B']);

        $tenancy->set($a->id);
        $this->assertSame(['Sucursal de A'], Branch::pluck('name')->all());

        $tenancy->set($b->id);
        $this->assertSame(['Sucursal de B'], Branch::pluck('name')->all());
    }

    public function test_company_id_se_autocompleta_al_crear(): void
    {
        $company = Company::factory()->create();

        app(Tenancy::class)->set($company->id);

        $branch = Branch::create(['name' => 'Centro']);

        $this->assertSame($company->id, $branch->company_id);
    }

    public function test_sin_empresa_activa_no_se_ve_nada(): void
    {
        Branch::factory()->count(3)->create();

        app(Tenancy::class)->set(null);

        // Fail-closed: sin empresa activa el resultado es vacío, nunca "todo".
        $this->assertSame(0, Branch::count());
    }

    public function test_modo_global_no_filtra(): void
    {
        Branch::factory()->count(3)->create();

        app(Tenancy::class)->unrestrict();

        $this->assertSame(3, Branch::count());
    }

    public function test_for_company_salta_el_scope(): void
    {
        $a = Company::factory()->create();
        $b = Company::factory()->create();

        Branch::factory()->count(2)->create(['company_id' => $a->id]);
        Branch::factory()->create(['company_id' => $b->id]);

        // Activa la empresa B, pero cuenta las de A: es lo que hace el panel
        // del operador y el cálculo de cupos.
        app(Tenancy::class)->set($b->id);

        $this->assertSame(2, Branch::forCompany($a->id)->count());
        $this->assertSame(3, Branch::allCompanies()->count());
    }

    public function test_run_for_restaura_el_estado_anterior(): void
    {
        $a = Company::factory()->create();
        $b = Company::factory()->create();

        $tenancy = app(Tenancy::class);
        $tenancy->set($a->id);

        $tenancy->runFor($b->id, fn () => $this->assertSame($b->id, $tenancy->id()));

        $this->assertSame($a->id, $tenancy->id());
    }

    /**
     * El route-model-binding de un modelo con CompanyScope tiene que resolverse
     * DESPUÉS de que SetTenant fije la empresa. Si el orden del grupo `web` se
     * rompe, el scope queda en fail-closed y todo ver/editar devuelve 404 al
     * propio dueño del recurso.
     */
    public function test_el_binding_de_un_recurso_propio_se_resuelve(): void
    {
        $company = $this->companyWithPlan();
        $branch = Branch::factory()->create(['company_id' => $company->id]);
        $user = $this->userInCompany($company, ['branches.view', 'branches.edit']);

        $this->actingInCompany($user, $company)->get(route('branches.show', $branch))->assertOk();
        $this->actingInCompany($user, $company)->get(route('branches.edit', $branch))->assertOk();
    }

    public function test_el_binding_de_un_recurso_ajeno_devuelve_404(): void
    {
        $mia = $this->companyWithPlan();
        $ajena = $this->companyWithPlan();
        $ajenaBranch = Branch::factory()->create(['company_id' => $ajena->id]);
        $user = $this->userInCompany($mia, ['branches.view', 'branches.edit']);

        // El scope no encuentra la sucursal de la otra empresa: 404, no 403,
        // para no filtrar siquiera que ese id existe.
        $this->actingInCompany($user, $mia)->get(route('branches.show', $ajenaBranch))->assertNotFound();
        $this->actingInCompany($user, $mia)->get(route('branches.edit', $ajenaBranch))->assertNotFound();
    }

    public function test_el_listado_web_solo_muestra_las_sucursales_de_la_empresa_activa(): void
    {
        $mia = $this->companyWithPlan();
        $ajena = $this->companyWithPlan();

        Branch::factory()->create(['company_id' => $mia->id, 'name' => 'Mi Sucursal']);
        Branch::factory()->create(['company_id' => $ajena->id, 'name' => 'Sucursal Ajena']);

        $user = $this->userInCompany($mia, ['branches.view']);

        $this->actingInCompany($user, $mia)
            ->get(route('branches.index'))
            ->assertOk()
            ->assertSee('Mi Sucursal')
            ->assertDontSee('Sucursal Ajena');
    }
}
