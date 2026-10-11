<?php

namespace Tests\Feature;

use App\Models\Cargo;
use App\Models\Company;
use App\Models\Personal;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Gestión de barberos (personal) desde el móvil.
 */
class ApiPersonalTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected Cargo $cargo;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = $this->companyWithPlan();

        $this->cargo = $this->inCompany($this->company, function () {
            $role = Role::factory()->ownedBy($this->company)->create(['name' => 'Barbero']);

            return Cargo::create([
                'company_id' => $this->company->id,
                'role_id' => $role->id,
                'name' => 'Barbero',
                'active' => true,
            ]);
        });

        $this->admin = $this->userInCompany($this->company, [
            'personal.view', 'personal.create', 'personal.edit',
        ], 'rrhh_api');
    }

    protected function headers(): array
    {
        return ['X-Company-Id' => (string) $this->company->id];
    }

    public function test_se_lista_el_personal_activo_e_inactivo(): void
    {
        $this->inCompany($this->company, function () {
            Personal::create(['company_id' => $this->company->id, 'full_name' => 'Tania Molina', 'active' => true, 'bookable' => true, 'cargo_id' => $this->cargo->id]);
            Personal::create(['company_id' => $this->company->id, 'full_name' => 'Diego Inactivo', 'active' => false, 'cargo_id' => $this->cargo->id]);
        });

        Sanctum::actingAs($this->admin);

        $names = collect($this->getJson('/api/v1/personal', $this->headers())->assertOk()->json('data'))
            ->pluck('full_name');

        $this->assertTrue($names->contains('Tania Molina'));
        $this->assertTrue($names->contains('Diego Inactivo'), 'La gestión incluye a los inactivos.');
    }

    public function test_se_crea_un_barbero(): void
    {
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/v1/personal', [
            'full_name' => 'Marco Vaca',
            'cargo_id' => $this->cargo->id,
            'phone' => '71234567',
            'specialty' => 'Degradados',
            'agenda_color' => '#E64A38',
            'bookable' => true,
            'active' => true,
        ], $this->headers())
            ->assertCreated()
            ->assertJsonPath('data.full_name', 'Marco Vaca')
            ->assertJsonPath('data.bookable', true);

        $this->assertDatabaseHas('personal', [
            'company_id' => $this->company->id,
            'full_name' => 'Marco Vaca',
            'bookable' => true,
        ]);
    }

    public function test_se_edita_un_barbero(): void
    {
        $barbero = $this->inCompany($this->company, fn () => Personal::create([
            'company_id' => $this->company->id, 'full_name' => 'Tania', 'active' => true, 'bookable' => true, 'cargo_id' => $this->cargo->id,
        ]));

        Sanctum::actingAs($this->admin);

        $this->putJson("/api/v1/personal/{$barbero->id}", [
            'full_name' => 'Tania Molina',
            'cargo_id' => $this->cargo->id,
            'bookable' => false,
            'active' => false,
        ], $this->headers())
            ->assertOk()
            ->assertJsonPath('data.bookable', false)
            ->assertJsonPath('data.active', false);

        $this->assertDatabaseHas('personal', [
            'id' => $barbero->id, 'full_name' => 'Tania Molina', 'bookable' => false, 'active' => false,
        ]);
    }

    public function test_no_se_crea_con_un_cargo_de_otra_barberia(): void
    {
        $otra = $this->companyWithPlan();
        $cargoAjeno = $this->inCompany($otra, function () use ($otra) {
            $role = Role::factory()->ownedBy($otra)->create(['name' => 'X']);
            return Cargo::create(['company_id' => $otra->id, 'role_id' => $role->id, 'name' => 'X', 'active' => true]);
        });

        Sanctum::actingAs($this->admin);

        $this->postJson('/api/v1/personal', [
            'full_name' => 'Intruso', 'cargo_id' => $cargoAjeno->id,
        ], $this->headers())->assertStatus(422)->assertJsonValidationErrors('cargo_id');
    }

    public function test_sin_permiso_no_se_gestiona(): void
    {
        $barbero = $this->userInCompany($this->company, ['appointments.view'], 'solo_mira');
        Sanctum::actingAs($barbero);

        $this->getJson('/api/v1/personal', $this->headers())->assertForbidden();
        $this->postJson('/api/v1/personal', [], $this->headers())->assertForbidden();
    }
}
