<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Caja;
use App\Models\Cargo;
use App\Models\Company;
use App\Models\PaymentMethod;
use App\Models\Personal;
use App\Models\Plan;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WorkSchedule;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Alta de empresa «lista para usar»: sucursal con almacén, cargos con sus
 * permisos, responsable con usuario, caja y horario, y las credenciales a la
 * vista una sola vez.
 */
class CompanySetupTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->superAdmin = User::factory()->superAdmin()->create();
    }

    /** @return array<string, mixed> */
    protected function payload(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'BARBERÍA EL FILO',
            'tax_id' => '1234500',
            'tax_id_label' => 'NIT',
            'country' => 'BO',
            'currency' => 'BOB',
            'timezone' => 'America/La_Paz',
            'address' => 'Av. Siempre Viva 123',
            'plan_id' => Plan::factory()->create(['features' => ['agenda', 'caja', 'pos']])->id,
            'active' => 1,

            'setup' => 1,
            'setup_branch_name' => 'central',
            'setup_owner_name' => 'Juan Pérez',
            'setup_owner_phone' => '70000000',
            'setup_owner_username' => 'juanp',
            'setup_owner_email' => 'juan@elfilo.test',
            'setup_owner_password' => 'Secreta123',
            'setup_owner_bookable' => 1,
            'setup_caja_name' => 'Caja principal',
            'setup_caja_to_owner' => 1,
            'setup_days' => [1, 2, 3, 4, 5, 6],
            'setup_open' => '09:00',
            'setup_close' => '19:00',
            'setup_base_cargos' => 1,
        ];
    }

    protected function created(): Company
    {
        return Company::where('tax_id', '1234500')->firstOrFail();
    }

    public function test_la_empresa_nace_lista_para_usar(): void
    {
        $this->actingAs($this->superAdmin)->post(route('companies.store'), $this->payload())
            ->assertRedirect();

        $company = $this->created();

        $this->inCompany($company, function () use ($company) {
            $branch = Branch::firstOrFail();
            $this->assertSame('CENTRAL', $branch->name);
            $this->assertSame('Av. Siempre Viva 123', $branch->address, 'Sin dirección propia, hereda la de la empresa.');
            $this->assertTrue(Warehouse::where('branch_id', $branch->id)->exists(), 'La sucursal trae su almacén.');

            $this->assertEqualsCanonicalizing(
                ['ADMINISTRADOR', 'RECEPCIONISTA', 'BARBERO'],
                Cargo::pluck('name')->all()
            );

            $owner = Personal::firstOrFail();
            $this->assertSame('JUAN PÉREZ', $owner->full_name);
            $this->assertSame('ADMINISTRADOR', $owner->cargo->name);
            $this->assertTrue($owner->bookable);

            $caja = Caja::firstOrFail();
            $this->assertSame('CAJA PRINCIPAL', $caja->name);
            $this->assertSame($owner->id, $caja->personal_id);
            $this->assertSame($branch->id, $caja->branch_id);

            $this->assertSame(6, WorkSchedule::where('personal_id', $owner->id)->count());
            $this->assertTrue(PaymentMethod::exists());
        });

        // El responsable entra y tiene los permisos de administrador.
        $user = User::where('name', 'juanp')->firstOrFail();
        $this->assertSame($user->id, $this->inCompany($company, fn () => Personal::first()->user_id));
        $this->assertTrue($user->hasPermissionInCompany('cash.edit', $company));
        $this->assertTrue($user->hasPermissionInCompany('dashboard.view', $company));
        $this->assertFalse($user->hasPermissionInCompany('companies.create', $company), 'Nada de plataforma.');
        $this->assertFalse($user->hasPermissionInCompany('users.create', $company));

        $this->post(route('logout'));
        $this->post(route('login'), ['email' => 'juanp', 'password' => 'Secreta123'])->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    public function test_la_recepcionista_y_el_barbero_traen_sus_permisos(): void
    {
        $this->actingAs($this->superAdmin)->post(route('companies.store'), $this->payload());

        $company = $this->created();

        [$recepcion, $barbero] = $this->inCompany($company, fn () => [
            Cargo::where('name', 'RECEPCIONISTA')->firstOrFail()->role->permissions()->pluck('slug'),
            Cargo::where('name', 'BARBERO')->firstOrFail()->role->permissions()->pluck('slug'),
        ]);

        $this->assertTrue($recepcion->contains('appointments.create'));
        $this->assertFalse($recepcion->contains('sales.delete'));
        $this->assertTrue($barbero->contains('appointments.edit'));
        $this->assertFalse($barbero->contains('cash.view'));
    }

    public function test_muestra_las_credenciales_una_sola_vez(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->followingRedirects()
            ->post(route('companies.store'), $this->payload());

        $response->assertOk()
            ->assertSee('Credenciales del responsable')
            ->assertSee('juanp')
            ->assertSee('Secreta123');

        // Al volver a abrir la ficha ya no están.
        $this->get(route('companies.show', $this->created()))
            ->assertOk()
            ->assertDontSee('Secreta123');
    }

    public function test_sin_responsable_que_atienda_no_hay_horario_ni_caja_asignada(): void
    {
        $this->actingAs($this->superAdmin)->post(route('companies.store'), $this->payload([
            'setup_owner_bookable' => 0,
            'setup_caja_to_owner' => 0,
            'setup_base_cargos' => 0,
        ]));

        $this->inCompany($this->created(), function () {
            $this->assertSame(0, WorkSchedule::count());
            $this->assertNull(Caja::firstOrFail()->personal_id);
            $this->assertSame(['ADMINISTRADOR'], Cargo::pluck('name')->all());
        });
    }

    public function test_un_usuario_repetido_no_deja_la_empresa_a_medias(): void
    {
        User::factory()->create(['name' => 'juanp']);

        $this->actingAs($this->superAdmin)->post(route('companies.store'), $this->payload())
            ->assertSessionHasErrors('setup_owner_username');

        $this->assertNull(Company::where('tax_id', '1234500')->first(), 'Ni la empresa se crea.');
    }

    public function test_el_formulario_de_alta_trae_la_puesta_en_marcha(): void
    {
        $this->actingAs($this->superAdmin)->get(route('companies.create'))
            ->assertOk()
            ->assertSee('Dejar la empresa lista para usar')
            ->assertSee('name="setup_owner_username"', false);
    }
}
