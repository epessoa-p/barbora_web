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
    public function test_el_admin_de_barberia_no_ve_las_otras_barberias_del_usuario(): void
    {
        $demo = $this->companyWithPlan(['name' => 'Barbería Demo']);
        $otra = $this->companyWithPlan(['name' => 'Barbería Secreta']);

        $admin = $this->userInCompany($demo, ['users.view'], 'admin_demo');

        // Un barbero que trabaja en las dos.
        $compartido = $this->userInCompany($demo, [], 'barbero');
        $compartido->companies()->attach($otra->id, [
            'role_id' => Role::where('slug', 'barbero')->value('id'),
            'active' => true,
        ]);

        $this->actingInCompany($admin, $demo)->get(route('users.index'))
            ->assertOk()
            ->assertSee($compartido->email)
            ->assertDontSee('Barbería Secreta');
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
}
