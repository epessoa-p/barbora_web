<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * El operador de la plataforma salta las cuatro capas y, sin empresa activa,
 * trabaja en Modo Global. Ver ARQUITECTURA §2.
 */
class SuperAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_en_modo_global_ve_los_datos_de_todas_las_empresas(): void
    {
        $a = $this->companyWithPlan();
        $b = $this->companyWithPlan();

        Branch::factory()->create(['company_id' => $a->id, 'name' => 'Sucursal Alfa']);
        Branch::factory()->create(['company_id' => $b->id, 'name' => 'Sucursal Beta']);

        $superAdmin = User::factory()->superAdmin()->create();

        // Sin current_company_id => Modo Global.
        $this->actingAs($superAdmin)
            ->get(route('branches.index'))
            ->assertOk()
            ->assertSee('Sucursal Alfa')
            ->assertSee('Sucursal Beta');
    }

    public function test_al_entrar_como_una_empresa_deja_de_ver_las_demas(): void
    {
        $a = $this->companyWithPlan();
        $b = $this->companyWithPlan();

        Branch::factory()->create(['company_id' => $a->id, 'name' => 'Sucursal Alfa']);
        Branch::factory()->create(['company_id' => $b->id, 'name' => 'Sucursal Beta']);

        $superAdmin = User::factory()->superAdmin()->create();

        $this->actingInCompany($superAdmin, $a)
            ->get(route('branches.index'))
            ->assertOk()
            ->assertSee('Sucursal Alfa')
            ->assertDontSee('Sucursal Beta');
    }

    public function test_puede_entrar_como_una_empresa_a_la_que_no_pertenece(): void
    {
        $company = $this->companyWithPlan();
        $superAdmin = User::factory()->superAdmin()->create();

        $this->actingAs($superAdmin)
            ->post(route('set-company', $company->id))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('current_company_id', $company->id);
    }

    public function test_puede_volver_al_modo_global(): void
    {
        $company = $this->companyWithPlan();
        $superAdmin = User::factory()->superAdmin()->create();

        $this->actingInCompany($superAdmin, $company)
            ->post(route('exit-company'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionMissing('current_company_id');
    }

    public function test_un_usuario_normal_no_puede_salir_al_modo_global(): void
    {
        $company = $this->companyWithPlan();
        $user = $this->userInCompany($company);

        $this->actingInCompany($user, $company)->post(route('exit-company'))->assertForbidden();
    }

    public function test_un_usuario_normal_no_puede_entrar_a_una_empresa_ajena(): void
    {
        $mia = $this->companyWithPlan();
        $ajena = Company::factory()->create();
        $user = $this->userInCompany($mia);

        $this->actingInCompany($user, $mia)->post(route('set-company', $ajena->id))->assertNotFound();
    }

    public function test_crear_una_empresa_le_crea_su_suscripcion_de_prueba(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $plan = \App\Models\Plan::factory()->create(['trial_days' => 20]);

        $this->actingAs($superAdmin)->post(route('companies.store'), [
            'name' => 'Barbería Nueva',
            'tax_id' => '5551234567',
            'tax_id_label' => 'NIT',
            'country' => 'BO',
            'currency' => 'BOB',
            'timezone' => 'America/La_Paz',
            'plan_id' => $plan->id,
            'active' => 1,
        ])->assertRedirect();

        $company = Company::where('tax_id', '5551234567')->firstOrFail();

        $this->assertNotNull($company->subscription);
        $this->assertSame('trial', $company->subscription->status);
        $this->assertSame($plan->id, $company->subscription->plan_id);
        $this->assertTrue($company->subscription->onTrial());
    }

    public function test_puede_ampliar_el_cupo_de_una_empresa_con_un_override(): void
    {
        $company = $this->companyWithPlan(planAttributes: ['max_branches' => 1]);
        $superAdmin = User::factory()->superAdmin()->create();

        $this->assertSame(1, $company->effectiveLimit('branches'));

        $this->actingAs($superAdmin)->put(route('companies.subscription.update', $company), [
            'plan_id' => $company->subscription->plan_id,
            'status' => 'active',
            'current_period_end' => now()->addMonth()->format('Y-m-d'),
            'grace_days' => 3,
            'max_branches_override' => 5,
        ])->assertRedirect(route('companies.show', $company));

        $this->assertSame(5, $company->refresh()->effectiveLimit('branches'));
    }

    public function test_al_crear_una_empresa_se_le_puede_subir_el_logo(): void
    {
        Storage::fake('public');
        $superAdmin = User::factory()->superAdmin()->create();

        $this->actingAs($superAdmin)->post(route('companies.store'), [
            'name' => 'Barbería con Logo',
            'tax_id' => '7778889990',
            'tax_id_label' => 'NIT',
            'country' => 'BO',
            'currency' => 'BOB',
            'timezone' => 'America/La_Paz',
            'active' => 1,
            'logo' => UploadedFile::fake()->image('logo.png', 300, 300),
        ])->assertRedirect();

        $company = Company::where('tax_id', '7778889990')->firstOrFail();

        $this->assertNotNull($company->logo);
        $this->assertStringStartsWith("companies/{$company->id}/", $company->logo);
        Storage::disk('public')->assertExists($company->logo);
    }

    public function test_al_editar_una_empresa_se_le_puede_subir_el_logo(): void
    {
        Storage::fake('public');
        $superAdmin = User::factory()->superAdmin()->create();
        $company = $this->companyWithPlan(['tax_id' => '1112223334']);

        $this->actingAs($superAdmin)->put(route('companies.update', $company), [
            'name' => $company->name,
            'tax_id' => $company->tax_id,
            'country' => 'BO',
            'currency' => 'BOB',
            'timezone' => 'America/La_Paz',
            'active' => 1,
            'logo' => UploadedFile::fake()->image('nuevo.png'),
        ])->assertRedirect();

        $company->refresh();

        $this->assertNotNull($company->logo);
        Storage::disk('public')->assertExists($company->logo);
    }
}
