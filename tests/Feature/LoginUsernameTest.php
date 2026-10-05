<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * El acceso admite correo O nombre de usuario.
 *
 * El nombre de usuario vive en users.name; el LoginController decide si lo que
 * se escribió es un correo (por el formato) o un usuario.
 */
class LoginUsernameTest extends TestCase
{
    use RefreshDatabase;

    protected function barbero(string $name, string $email): User
    {
        $company = $this->companyWithPlan();
        $role = Role::firstOrCreate(['company_id' => null, 'slug' => 'barbero'], ['name' => 'Barbero']);

        $user = User::factory()->create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make('Secreto@1234'),
            'active' => true,
        ]);
        $user->companies()->attach($company->id, ['role_id' => $role->id, 'active' => true]);

        return $user;
    }

    public function test_se_inicia_sesion_con_el_email(): void
    {
        $user = $this->barbero('tania_molina', 'tania@demo.test');

        $this->post(route('login.store'), ['email' => 'tania@demo.test', 'password' => 'Secreto@1234'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_se_inicia_sesion_con_el_nombre_de_usuario(): void
    {
        $user = $this->barbero('tania_molina', 'tania@demo.test');

        $this->post(route('login.store'), ['email' => 'tania_molina', 'password' => 'Secreto@1234'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_un_usuario_que_no_existe_no_entra(): void
    {
        $this->barbero('tania_molina', 'tania@demo.test');

        $this->post(route('login.store'), ['email' => 'no_existe', 'password' => 'Secreto@1234'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }
}
