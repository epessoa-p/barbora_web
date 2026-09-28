<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tercera capa: el permiso del rol en esa empresa. Ver ARQUITECTURA §5.
 */
class PermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_sin_el_permiso_no_entra(): void
    {
        $company = $this->companyWithPlan();
        $user = $this->userInCompany($company, ['branches.view']);   // ve, pero no crea

        $this->actingInCompany($user, $company)->get(route('branches.index'))->assertOk();
        $this->actingInCompany($user, $company)->get(route('branches.create'))->assertForbidden();
    }

    public function test_el_superadmin_salta_el_permiso(): void
    {
        $company = $this->companyWithPlan();
        $superAdmin = User::factory()->superAdmin()->create();

        $this->actingInCompany($superAdmin, $company)->get(route('branches.create'))->assertOk();
    }

    public function test_un_usuario_normal_no_entra_al_panel_del_operador(): void
    {
        $company = $this->companyWithPlan();
        $user = $this->userInCompany($company, ['branches.view']);

        $this->actingInCompany($user, $company)->get(route('companies.index'))->assertForbidden();
        $this->actingInCompany($user, $company)->get(route('plans.index'))->assertForbidden();
    }

    public function test_los_permisos_son_por_empresa(): void
    {
        $conPermiso = $this->companyWithPlan();
        $sinPermiso = $this->companyWithPlan();

        $user = $this->userInCompany($conPermiso, ['branches.view'], 'con-permiso');
        // En la segunda empresa el usuario tiene otro rol, sin ese permiso.
        $otroRol = $this->userInCompany($sinPermiso, ['cajas.view'], 'sin-permiso');
        $user->companies()->attach($sinPermiso->id, [
            'role_id' => $otroRol->companies()->first()->pivot->role_id,
            'active' => true,
        ]);

        $this->actingInCompany($user, $conPermiso)->get(route('branches.index'))->assertOk();
        $this->actingInCompany($user, $sinPermiso)->get(route('branches.index'))->assertForbidden();
    }

    public function test_un_permiso_directo_amplia_al_rol(): void
    {
        $company = $this->companyWithPlan();
        $user = $this->userInCompany($company, ['branches.view']);

        $this->actingInCompany($user, $company)->get(route('branches.create'))->assertForbidden();

        // Excepción puntual, sin crear un rol nuevo (tabla user_permission).
        $permission = \App\Models\Permission::firstOrCreate(
            ['slug' => 'branches.create'],
            ['name' => 'Crear Sucursales', 'module' => 'branches']
        );
        \Illuminate\Support\Facades\DB::table('user_permission')->insert([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'permission_id' => $permission->id,
        ]);

        $this->actingInCompany($user, $company)->get(route('branches.create'))->assertOk();
    }
}
