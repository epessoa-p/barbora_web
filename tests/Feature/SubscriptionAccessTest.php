<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Primera capa de autorización: el estado de la suscripción, derivado de las
 * fechas. Ver ARQUITECTURA §7.2.
 */
class SubscriptionAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_en_prueba_vigente_entra_y_escribe(): void
    {
        $company = $this->companyWithPlan(subscriptionAttributes: [
            'status' => 'trial',
            'trial_ends_at' => now()->addDays(7),
            'current_period_end' => null,
        ]);
        $user = $this->userInCompany($company, ['branches.view', 'branches.create']);

        $this->actingInCompany($user, $company)->get(route('branches.index'))->assertOk();

        $this->actingInCompany($user, $company)
            ->post(route('branches.store'), ['name' => 'Centro', 'active' => 1])
            ->assertRedirect(route('branches.index'));

        $this->assertSame(1, Branch::allCompanies()->count());
    }

    public function test_al_dia_entra_y_escribe(): void
    {
        $company = $this->companyWithPlan();
        $user = $this->userInCompany($company, ['branches.view']);

        $this->assertTrue($company->subscription->allowsWrite());
        $this->actingInCompany($user, $company)->get(route('branches.index'))->assertOk();
    }

    public function test_vencida_dentro_de_la_gracia_entra_pero_no_escribe(): void
    {
        $company = $this->companyWithPlan(subscriptionAttributes: [
            'status' => 'active',
            'current_period_end' => now()->subDay(),
            'grace_days' => 3,
        ]);
        $user = $this->userInCompany($company, ['branches.view', 'branches.create']);

        $this->assertTrue($company->subscription->inGrace());

        $this->actingInCompany($user, $company)->get(route('branches.index'))->assertOk();

        $this->actingInCompany($user, $company)
            ->post(route('branches.store'), ['name' => 'Centro', 'active' => 1])
            ->assertSessionHasErrors('error');

        $this->assertSame(0, Branch::allCompanies()->count());
    }

    public function test_vencida_pasada_la_gracia_no_entra(): void
    {
        $company = $this->companyWithPlan(subscriptionAttributes: [
            'status' => 'active',
            'current_period_end' => now()->subDays(30),
            'grace_days' => 3,
        ]);
        $user = $this->userInCompany($company, ['branches.view']);

        $this->actingInCompany($user, $company)
            ->get(route('branches.index'))
            ->assertRedirect(route('subscription.blocked'));
    }

    public function test_suspendida_no_entra(): void
    {
        $company = $this->companyWithPlan(subscriptionAttributes: ['status' => 'suspended']);
        $user = $this->userInCompany($company, ['branches.view']);

        $this->actingInCompany($user, $company)
            ->get(route('branches.index'))
            ->assertRedirect(route('subscription.blocked'));
    }

    public function test_la_pantalla_de_bloqueo_es_accesible_para_no_quedar_atrapado(): void
    {
        $company = $this->companyWithPlan(subscriptionAttributes: ['status' => 'cancelled']);
        $user = $this->userInCompany($company, ['branches.view']);

        $this->actingInCompany($user, $company)->get(route('subscription.blocked'))->assertOk();
        $this->actingInCompany($user, $company)->post(route('logout'))->assertRedirect('/login');
    }

    public function test_el_superadmin_no_esta_sujeto_a_suscripcion(): void
    {
        $company = $this->companyWithPlan(subscriptionAttributes: ['status' => 'suspended']);
        $superAdmin = User::factory()->superAdmin()->create();

        $this->actingInCompany($superAdmin, $company)
            ->get(route('branches.index'))
            ->assertOk();
    }
}
