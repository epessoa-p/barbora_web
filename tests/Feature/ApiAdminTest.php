<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Caja;
use App\Models\Cargo;
use App\Models\Company;
use App\Models\PaymentMethod;
use App\Models\Personal;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * La sección «Administración» del móvil: mi empresa, sucursales, cajas y
 * métodos de pago. Mismas reglas y permisos que la web.
 */
class ApiAdminTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = $this->companyWithPlan();

        $this->admin = $this->userInCompany($this->company, [
            'settings.view', 'settings.edit',
            'branches.view', 'branches.create', 'branches.edit',
            'cajas.view', 'cajas.create', 'cajas.edit',
        ], 'admin_api');
    }

    protected function headers(): array
    {
        return ['X-Company-Id' => (string) $this->company->id];
    }

    public function test_se_editan_los_datos_de_la_barberia(): void
    {
        Sanctum::actingAs($this->admin);

        $this->putJson('/api/v1/admin/company', [
            'name' => 'Barbería Central',
            'address' => 'Av. Busch 123',
            'phone' => '70000000',
            'receipt_footer' => 'Vuelve pronto',
        ], $this->headers())
            ->assertOk()
            ->assertJsonPath('data.name', 'Barbería Central');

        $this->assertDatabaseHas('companies', [
            'id' => $this->company->id, 'name' => 'Barbería Central', 'address' => 'Av. Busch 123',
        ]);
    }

    public function test_se_crea_y_edita_una_sucursal_con_codigo_automatico(): void
    {
        Sanctum::actingAs($this->admin);

        $id = $this->postJson('/api/v1/admin/branches', ['name' => 'Norte'], $this->headers())
            ->assertCreated()
            ->assertJsonPath('data.active', true)
            ->json('data.id');

        $code = Branch::withoutGlobalScopes()->find($id)->code;
        $this->assertNotEmpty($code, 'Sin código, el sistema lo genera.');

        // Vacío al editar no borra el código.
        $this->putJson("/api/v1/admin/branches/{$id}", ['name' => 'Norte 2', 'code' => '', 'active' => false], $this->headers())
            ->assertOk()
            ->assertJsonPath('data.code', $code)
            ->assertJsonPath('data.active', false);
    }

    public function test_la_caja_y_su_responsable_son_de_la_misma_sucursal(): void
    {
        [$norte, $sur, $tania] = $this->inCompany($this->company, function () {
            $norte = Branch::create(['company_id' => $this->company->id, 'name' => 'Norte', 'active' => true]);
            $sur = Branch::create(['company_id' => $this->company->id, 'name' => 'Sur', 'active' => true]);
            $tania = Personal::create(['company_id' => $this->company->id, 'full_name' => 'TANIA', 'branch_id' => $sur->id, 'active' => true]);

            return [$norte, $sur, $tania];
        });

        Sanctum::actingAs($this->admin);

        $this->postJson('/api/v1/admin/cajas', [
            'name' => 'Caja 1', 'branch_id' => $norte->id, 'personal_id' => $tania->id,
        ], $this->headers())->assertStatus(422)->assertJsonValidationErrors('personal_id');

        $id = $this->postJson('/api/v1/admin/cajas', [
            'name' => 'Caja 1', 'branch_id' => $sur->id, 'personal_id' => $tania->id,
        ], $this->headers())
            ->assertCreated()
            ->assertJsonPath('data.branch', 'Sur')
            ->json('data.id');

        $this->putJson("/api/v1/admin/cajas/{$id}", [
            'name' => 'Caja Sur', 'branch_id' => $sur->id, 'active' => false,
        ], $this->headers())->assertOk()->assertJsonPath('data.active', false);

        $this->assertFalse(Caja::withoutGlobalScopes()->find($id)->active);
    }

    public function test_metodos_de_pago_incluye_los_inactivos_y_protege_el_efectivo(): void
    {
        Sanctum::actingAs($this->admin);

        $id = $this->postJson('/api/v1/admin/payment-methods', [
            'name' => 'Tigo Money', 'requires_reference' => true, 'active' => false,
        ], $this->headers())
            ->assertCreated()
            ->assertJsonPath('data.slug', 'tigo_money')
            ->json('data.id');

        $names = collect($this->getJson('/api/v1/admin/payment-methods', $this->headers())->assertOk()->json('data'))
            ->pluck('name');
        $this->assertTrue($names->contains('Tigo Money'), 'La gestión incluye los dados de baja.');

        $this->putJson("/api/v1/admin/payment-methods/{$id}", ['name' => 'Tigo'], $this->headers())->assertOk();

        // El único que cuenta como efectivo no puede dejar de serlo.
        $cash = PaymentMethod::withoutGlobalScopes()->where('company_id', $this->company->id)
            ->where('counts_as_cash', true)->get();

        if ($cash->count() === 1) {
            $this->putJson("/api/v1/admin/payment-methods/{$cash->first()->id}", [
                'name' => 'Efectivo', 'counts_as_cash' => false,
            ], $this->headers())->assertStatus(422)->assertJsonValidationErrors('counts_as_cash');
        }
    }

    public function test_el_personal_guarda_su_sucursal_base(): void
    {
        [$sur, $cargo] = $this->inCompany($this->company, function () {
            $sur = Branch::create(['company_id' => $this->company->id, 'name' => 'Sur', 'active' => true]);
            $role = Role::factory()->ownedBy($this->company)->create(['name' => 'Barbero']);
            $cargo = Cargo::create([
                'company_id' => $this->company->id, 'role_id' => $role->id, 'name' => 'Barbero', 'active' => true,
            ]);

            return [$sur, $cargo];
        });

        $user = $this->userInCompany($this->company, ['personal.view', 'personal.create'], 'rrhh_suc');
        Sanctum::actingAs($user);

        $options = $this->getJson('/api/v1/personal/options', $this->headers())->assertOk();
        $this->assertContains('Sur', collect($options->json('branches'))->pluck('name'));
        $this->assertContains('Barbero', collect($options->json('cargos'))->pluck('name'));

        $id = $this->postJson('/api/v1/personal', [
            'full_name' => 'MARCO', 'cargo_id' => $cargo->id, 'branch_id' => $sur->id,
        ], $this->headers())
            ->assertCreated()
            ->assertJsonPath('data.branch', 'Sur')
            ->json('data.id');

        $this->assertSame($sur->id, (int) Personal::withoutGlobalScopes()->find($id)->branch_id);
    }

    public function test_sin_permiso_no_se_ve_nada(): void
    {
        $user = $this->userInCompany($this->company, ['appointments.view'], 'solo_mira');
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/admin/company', $this->headers())->assertForbidden();
        $this->getJson('/api/v1/admin/branches', $this->headers())->assertForbidden();
        $this->getJson('/api/v1/admin/cajas', $this->headers())->assertForbidden();
        $this->getJson('/api/v1/admin/payment-methods', $this->headers())->assertForbidden();
        $this->putJson('/api/v1/admin/company', ['name' => 'X'], $this->headers())->assertForbidden();
    }
}
