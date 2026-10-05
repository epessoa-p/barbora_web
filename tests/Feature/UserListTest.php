<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El listado de usuarios muestra a qué barbería pertenece cada uno y con qué
 * rol, que es lo que sale de company_user.
 */
class UserListTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_superadmin_ve_la_barberia_y_el_rol_de_cada_usuario(): void
    {
        $demo = $this->companyWithPlan(['name' => 'Barbería Demo']);
        $prueba = $this->companyWithPlan(['name' => 'Barbería Prueba']);

        $this->userInCompany($demo, [], 'cajero')->update(['email' => 'cajero@demo.test']);
        $this->userInCompany($prueba, [], 'recepcion')->update(['email' => 'recep@prueba.test']);

        $super = User::factory()->create(['is_super_admin' => true]);

        $this->actingAs($super)->get(route('users.index'))
            ->assertOk()
            ->assertSee('Barbería Demo')
            ->assertSee('Barbería Prueba')
            ->assertSee('Cajero')
            ->assertSee('Recepcion');
    }

    public function test_el_superadmin_filtra_por_barberia(): void
    {
        $demo = $this->companyWithPlan(['name' => 'Barbería Demo']);
        $prueba = $this->companyWithPlan(['name' => 'Barbería Prueba']);

        $this->userInCompany($demo, [], 'cajero')->update(['email' => 'cajero@demo.test']);
        $this->userInCompany($prueba, [], 'recepcion')->update(['email' => 'recep@prueba.test']);

        $super = User::factory()->create(['is_super_admin' => true]);

        $this->actingAs($super)->get(route('users.index', ['company_id' => $demo->id]))
            ->assertOk()
            ->assertSee('cajero@demo.test')
            ->assertDontSee('recep@prueba.test');
    }

    /**
     * Si un usuario trabaja en dos barberías, el admin de una no tiene por qué
     * saber que también está en la otra.
     */
    public function test_una_empresa_no_accede_a_la_pantalla_de_usuarios(): void
    {
        // Usuarios es pantalla del operador del SaaS: solo el superadmin. Una
        // empresa gestiona su gente desde Personal, no desde aquí, aunque tenga
        // el permiso users.view.
        $demo = $this->companyWithPlan(['name' => 'Barbería Demo']);
        $admin = $this->userInCompany($demo, ['users.view'], 'admin_demo');

        $this->actingInCompany($admin, $demo)->get(route('users.index'))->assertForbidden();
    }

    public function test_un_usuario_sin_barberia_se_senala(): void
    {
        User::factory()->create(['email' => 'huerfano@test.test']);
        $super = User::factory()->create(['is_super_admin' => true]);

        $this->actingAs($super)->get(route('users.index'))
            ->assertOk()
            ->assertSee('huerfano@test.test')
            ->assertSee('Sin barbería');
    }

    public function test_se_edita_un_usuario_conservando_su_email(): void
    {
        $super = User::factory()->create(['is_super_admin' => true]);
        $user = User::factory()->create(['name' => 'barbero1', 'email' => 'barbero@demo.test']);

        // Reproduce el caso del formulario: el email viaja con su valor actual.
        $this->actingAs($super)
            ->put(route('users.update', $user), [
                'name' => 'barbero_editado',
                'email' => 'barbero@demo.test',
                'phone' => '70000000',
            ])
            ->assertRedirect(route('users.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'barbero_editado',
            'email' => 'barbero@demo.test',
            'phone' => '70000000',
        ]);
    }
}
