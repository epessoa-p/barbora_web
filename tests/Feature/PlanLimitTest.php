<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cuarta capa: el cupo del plan, comprobado solo al crear.
 * Ver ARQUITECTURA §8 y §9.
 */
class PlanLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_al_alcanzar_el_limite_no_deja_crear(): void
    {
        $company = $this->companyWithPlan(planAttributes: ['max_branches' => 1]);
        $user = $this->userInCompany($company, ['branches.view', 'branches.create']);

        $this->actingInCompany($user, $company)
            ->post(route('branches.store'), ['name' => 'Primera', 'active' => 1])
            ->assertRedirect(route('branches.index'));

        $this->actingInCompany($user, $company)
            ->post(route('branches.store'), ['name' => 'Segunda', 'active' => 1])
            ->assertSessionHasErrors('error');

        $this->assertSame(1, Branch::allCompanies()->count());
    }

    public function test_el_override_de_la_empresa_gana_al_limite_del_plan(): void
    {
        $company = $this->companyWithPlan(
            planAttributes: ['max_branches' => 1],
            subscriptionAttributes: ['max_branches_override' => 3],
        );
        $user = $this->userInCompany($company, ['branches.view', 'branches.create']);

        foreach (['Primera', 'Segunda', 'Tercera'] as $name) {
            $this->actingInCompany($user, $company)
                ->post(route('branches.store'), ['name' => $name, 'active' => 1])
                ->assertRedirect(route('branches.index'));
        }

        $this->assertSame(3, Branch::allCompanies()->count());

        $this->actingInCompany($user, $company)
            ->post(route('branches.store'), ['name' => 'Cuarta', 'active' => 1])
            ->assertSessionHasErrors('error');

        $this->assertSame(3, Branch::allCompanies()->count());
    }

    public function test_sin_limite_en_el_plan_es_ilimitado(): void
    {
        $company = $this->companyWithPlan(planAttributes: ['max_branches' => null]);

        $this->assertNull($company->effectiveLimit('branches'));
        $this->assertTrue($company->withinLimit('branches'));

        Branch::factory()->count(20)->create(['company_id' => $company->id]);

        $this->assertTrue($company->refresh()->withinLimit('branches'));
    }

    public function test_un_override_en_cero_no_permite_ninguna(): void
    {
        $company = $this->companyWithPlan(
            planAttributes: ['max_branches' => 5],
            subscriptionAttributes: ['max_branches_override' => 0],
        );

        $this->assertSame(0, $company->effectiveLimit('branches'));
        $this->assertFalse($company->withinLimit('branches'));
    }

    public function test_el_superadmin_no_tiene_tope(): void
    {
        $company = $this->companyWithPlan(planAttributes: ['max_branches' => 1]);
        $superAdmin = User::factory()->superAdmin()->create();

        foreach (['Primera', 'Segunda'] as $name) {
            $this->actingInCompany($superAdmin, $company)
                ->post(route('branches.store'), ['company_id' => $company->id, 'name' => $name, 'active' => 1])
                ->assertRedirect(route('branches.index'));
        }

        $this->assertSame(2, Branch::allCompanies()->count());
    }

    public function test_el_cupo_se_calcula_por_empresa_no_por_el_tenant_activo(): void
    {
        $a = $this->companyWithPlan(planAttributes: ['max_branches' => 2]);
        $b = $this->companyWithPlan(planAttributes: ['max_branches' => 2]);

        Branch::factory()->count(2)->create(['company_id' => $a->id]);

        // Con el scope activo, un where() ingenuo daría 0 aquí y el cupo
        // nunca se alcanzaría. usageFor() usa forCompany() por eso.
        app(\App\Support\Tenancy::class)->set($b->id);

        $this->assertSame(2, $a->usageFor('branches'));
        $this->assertFalse($a->withinLimit('branches'));
        $this->assertSame(0, $b->usageFor('branches'));
        $this->assertTrue($b->withinLimit('branches'));
    }
}
