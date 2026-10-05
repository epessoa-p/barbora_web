<?php

namespace Tests\Feature;

use App\Models\Cargo;
use App\Models\Company;
use App\Models\Personal;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La cuenta de acceso del personal es opcional.
 *
 * No todo el que trabaja en la barbería entra al sistema. El alta de personal
 * puede crear (o no) un usuario; cuando lo crea, el nombre de usuario se guarda
 * en users.name y sirve para iniciar sesión (ver LoginController).
 */
class PersonalAccountTest extends TestCase
{
    use RefreshDatabase;

    protected function cargo(Company $company): Cargo
    {
        $role = Role::factory()->ownedBy($company)->create(['name' => 'Barbero']);

        return $this->inCompany($company, fn () => Cargo::create([
            'company_id' => $company->id,
            'role_id' => $role->id,
            'name' => 'Barbero',
            'active' => true,
        ]));
    }

    public function test_la_pantalla_de_alta_muestra_la_cuenta_opcional(): void
    {
        $company = $this->companyWithPlan();
        $this->cargo($company);
        $admin = $this->userInCompany($company, ['personal.create'], 'rrhh');

        $this->actingInCompany($admin, $company)
            ->get(route('personal.create'))
            ->assertOk()
            ->assertSee('Crear cuenta de acceso')
            ->assertSee('Nombre de usuario');
    }

    public function test_se_crea_personal_sin_cuenta_de_acceso(): void
    {
        $company = $this->companyWithPlan();
        $cargo = $this->cargo($company);
        $admin = $this->userInCompany($company, ['personal.create'], 'rrhh');

        // Sin "create_user": no se crea usuario.
        $this->actingInCompany($admin, $company)
            ->post(route('personal.store'), [
                'cargo_id' => $cargo->id,
                'full_name' => 'Marco Vaca',
                'active' => 1,
            ])
            ->assertRedirect(route('personal.index'));

        $personal = Personal::allCompanies()->where('full_name', 'Marco Vaca')->firstOrFail();

        $this->assertNull($personal->user_id, 'No debe quedar con cuenta de acceso.');
        $this->assertDatabaseMissing('users', ['email' => 'marco@demo.test']);
    }

    public function test_se_crea_personal_con_cuenta_y_username_propio(): void
    {
        $company = $this->companyWithPlan();
        $cargo = $this->cargo($company);
        $admin = $this->userInCompany($company, ['personal.create'], 'rrhh');

        $this->actingInCompany($admin, $company)
            ->post(route('personal.store'), [
                'cargo_id' => $cargo->id,
                'full_name' => 'Tania Molina',
                'create_user' => 1,
                'username' => 'tania',
                'email' => 'tania@demo.test',
                'password' => 'Secreto@1234',
                'password_confirmation' => 'Secreto@1234',
                'active' => 1,
            ])
            ->assertRedirect(route('personal.index'));

        $personal = Personal::allCompanies()->where('full_name', 'Tania Molina')->firstOrFail();

        $this->assertNotNull($personal->user_id);
        $this->assertSame('tania', $personal->user->name, 'El username es el que se escribió, no un slug.');
        $this->assertSame('tania@demo.test', $personal->user->email);
    }

    public function test_el_nombre_de_usuario_no_se_repite(): void
    {
        $company = $this->companyWithPlan();
        $cargo = $this->cargo($company);
        User::factory()->create(['name' => 'tania', 'email' => 'otra@demo.test']);
        $admin = $this->userInCompany($company, ['personal.create'], 'rrhh');

        $this->actingInCompany($admin, $company)
            ->post(route('personal.store'), [
                'cargo_id' => $cargo->id,
                'full_name' => 'Tania Molina',
                'create_user' => 1,
                'username' => 'tania',
                'email' => 'tania@demo.test',
                'password' => 'Secreto@1234',
                'password_confirmation' => 'Secreto@1234',
                'active' => 1,
            ])
            ->assertSessionHasErrors('username');

        $this->assertDatabaseMissing('users', ['email' => 'tania@demo.test']);
    }
}
