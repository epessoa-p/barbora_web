<?php

namespace Tests\Feature;

use App\Models\Cargo;
use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Los roles tienen dueño.
 *
 * Éste es el test de la deuda que se arrastraba desde el sistema base: el
 * formulario de cargos dejaba que el administrador de una barbería reescribiera
 * un rol del catálogo global, cambiando los permisos de TODAS las empresas del
 * SaaS. Si alguno de estos tests vuelve a fallar, el agujero está reabierto.
 */
class RoleOwnershipTest extends TestCase
{
    use RefreshDatabase;

    protected Company $barberiaA;
    protected Company $barberiaB;
    protected Role $rolDelSistema;
    protected User $adminA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->barberiaA = $this->companyWithPlan(['name' => 'Barbería A']);
        $this->barberiaB = $this->companyWithPlan(['name' => 'Barbería B']);

        // Rol compartido del catálogo del operador.
        $this->rolDelSistema = Role::factory()->create([
            'name' => 'Barbero',
            'slug' => 'barber',
        ]);
        $this->rolDelSistema->permissions()->sync([$this->permission('appointments.view')->id]);

        $this->adminA = $this->userInCompany(
            $this->barberiaA,
            ['cargos.view', 'cargos.create', 'cargos.edit'],
            'admin_a'
        );
    }

    /* ---------------------------------------------------------------------
     | El agujero original
     |--------------------------------------------------------------------- */

    public function test_una_empresa_no_reescribe_los_permisos_de_un_rol_del_sistema(): void
    {
        $cargo = $this->cargoEn($this->barberiaA, 'Barbero', $this->rolDelSistema);
        $sales = $this->permission('sales.create');

        $response = $this->actingInCompany($this->adminA, $this->barberiaA)
            ->put(route('cargos.update', $cargo), [
                'role_mode' => 'existing',
                'role_id' => $this->rolDelSistema->id,
                'name' => 'Barbero',
                'active' => 1,
                'permissions' => [$sales->id],
            ]);

        $response->assertSessionHasErrors('permissions');

        // El rol global sigue exactamente igual para todo el SaaS.
        $this->assertSame(
            ['appointments.view'],
            $this->rolDelSistema->permissions()->pluck('slug')->all()
        );
    }

    public function test_una_empresa_si_edita_los_permisos_de_un_rol_propio(): void
    {
        $rolPropio = Role::factory()->ownedBy($this->barberiaA)->create(['name' => 'Supervisor']);
        $cargo = $this->cargoEn($this->barberiaA, 'Supervisor', $rolPropio);
        $sales = $this->permission('sales.create');

        $this->actingInCompany($this->adminA, $this->barberiaA)
            ->put(route('cargos.update', $cargo), [
                'role_mode' => 'existing',
                'role_id' => $rolPropio->id,
                'name' => 'Supervisor',
                'active' => 1,
                'permissions' => [$sales->id],
            ])
            ->assertRedirect(route('cargos.index'));

        $this->assertSame(['sales.create'], $rolPropio->permissions()->pluck('slug')->all());
    }

    public function test_un_rol_creado_desde_un_cargo_pertenece_a_la_empresa(): void
    {
        $this->actingInCompany($this->adminA, $this->barberiaA)
            ->post(route('cargos.store'), [
                'role_mode' => 'new',
                'new_role_name' => 'Jefe de piso',
                'name' => 'Jefe de piso',
                'active' => 1,
                'permissions' => [$this->permission('appointments.view')->id],
            ])
            ->assertRedirect(route('cargos.index'));

        $rol = Role::where('name', 'Jefe de piso')->firstOrFail();

        $this->assertSame($this->barberiaA->id, $rol->company_id, 'El rol nuevo debe nacer dentro de la empresa.');
        $this->assertFalse($rol->isSystem());
    }

    /* ---------------------------------------------------------------------
     | Escalada de privilegios
     |--------------------------------------------------------------------- */

    public function test_no_se_asigna_el_rol_super_admin_a_un_cargo(): void
    {
        $superAdmin = Role::factory()->create(['name' => 'Super Administrador', 'slug' => Role::SUPER_ADMIN]);
        $superAdmin->permissions()->sync(Permission::pluck('id'));

        $this->actingInCompany($this->adminA, $this->barberiaA)
            ->post(route('cargos.store'), [
                'role_mode' => 'existing',
                'role_id' => $superAdmin->id,
                'name' => 'Dueño',
                'active' => 1,
            ])
            ->assertSessionHasErrors('role_id');

        $this->assertDatabaseMissing('cargos', ['name' => 'Dueño']);
    }

    public function test_no_se_conceden_permisos_de_plataforma_desde_un_cargo(): void
    {
        $rolPropio = Role::factory()->ownedBy($this->barberiaA)->create(['name' => 'Supervisor']);
        $cargo = $this->cargoEn($this->barberiaA, 'Supervisor', $rolPropio);

        $platform = $this->permission('companies.create');
        $legit = $this->permission('appointments.view');

        $this->actingInCompany($this->adminA, $this->barberiaA)
            ->put(route('cargos.update', $cargo), [
                'role_mode' => 'existing',
                'role_id' => $rolPropio->id,
                'name' => 'Supervisor',
                'active' => 1,
                'permissions' => [$platform->id, $legit->id],
            ])
            ->assertRedirect(route('cargos.index'));

        $this->assertSame(
            ['appointments.view'],
            $rolPropio->permissions()->pluck('slug')->all(),
            'Los permisos del operador no se conceden desde un cargo.'
        );
    }

    /* ---------------------------------------------------------------------
     | Aislamiento entre empresas
     |--------------------------------------------------------------------- */

    public function test_una_empresa_solo_ve_sus_roles_y_los_del_sistema(): void
    {
        $propio = Role::factory()->ownedBy($this->barberiaA)->create(['name' => 'Mi Supervisor']);
        $ajeno = Role::factory()->ownedBy($this->barberiaB)->create(['name' => 'Supervisor Ajeno']);
        $superAdmin = Role::factory()->create(['name' => 'Super Administrador', 'slug' => Role::SUPER_ADMIN]);

        $response = $this->actingInCompany($this->adminA, $this->barberiaA)
            ->get(route('cargos.create'))
            ->assertOk();

        $roles = $response->viewData('roles')->pluck('id');

        $this->assertTrue($roles->contains($propio->id), 'Debe ver su propio rol.');
        $this->assertTrue($roles->contains($this->rolDelSistema->id), 'Debe ver los del sistema.');
        $this->assertFalse($roles->contains($ajeno->id), 'No debe ver el rol de otra barbería.');
        $this->assertFalse($roles->contains($superAdmin->id), 'No debe ver el rol del operador.');
    }

    public function test_no_se_asigna_un_rol_de_otra_empresa(): void
    {
        $ajeno = Role::factory()->ownedBy($this->barberiaB)->create(['name' => 'Supervisor Ajeno']);

        $this->actingInCompany($this->adminA, $this->barberiaA)
            ->post(route('cargos.store'), [
                'role_mode' => 'existing',
                'role_id' => $ajeno->id,
                'name' => 'Copiado',
                'active' => 1,
            ])
            ->assertSessionHasErrors('role_id');

        $this->assertDatabaseMissing('cargos', ['name' => 'Copiado']);
    }

    public function test_no_se_leen_los_permisos_de_un_rol_ajeno(): void
    {
        $ajeno = Role::factory()->ownedBy($this->barberiaB)->create(['name' => 'Supervisor Ajeno']);
        $superAdmin = Role::factory()->create(['name' => 'Super Administrador', 'slug' => Role::SUPER_ADMIN]);

        $this->actingInCompany($this->adminA, $this->barberiaA)
            ->get(route('cargos.role-permissions', $ajeno))
            ->assertNotFound();

        $this->actingInCompany($this->adminA, $this->barberiaA)
            ->get(route('cargos.role-permissions', $superAdmin))
            ->assertNotFound();

        $this->actingInCompany($this->adminA, $this->barberiaA)
            ->get(route('cargos.role-permissions', $this->rolDelSistema))
            ->assertOk()
            ->assertJsonPath('editable', false);
    }

    public function test_dos_empresas_pueden_llamar_igual_a_su_rol(): void
    {
        $adminB = $this->userInCompany($this->barberiaB, ['cargos.create'], 'admin_b');

        foreach ([[$this->adminA, $this->barberiaA], [$adminB, $this->barberiaB]] as [$user, $company]) {
            $this->actingInCompany($user, $company)
                ->post(route('cargos.store'), [
                    'role_mode' => 'new',
                    'new_role_name' => 'Supervisor',
                    'name' => 'Supervisor',
                    'active' => 1,
                ])
                ->assertRedirect(route('cargos.index'));
        }

        $slugs = Role::where('name', 'Supervisor')->orderBy('company_id')->pluck('slug', 'company_id');

        $this->assertCount(2, $slugs);
        $this->assertSame('supervisor', $slugs[$this->barberiaA->id]);
        $this->assertSame('supervisor', $slugs[$this->barberiaB->id], 'El slug no compite entre empresas.');
    }

    /* ---------------------------------------------------------------------
     | Catálogo global (pantalla del superadmin)
     |--------------------------------------------------------------------- */

    public function test_el_catalogo_global_no_muestra_los_roles_de_una_empresa(): void
    {
        $propio = Role::factory()->ownedBy($this->barberiaA)->create(['name' => 'Mi Supervisor']);
        $superUser = User::factory()->create(['is_super_admin' => true]);

        $response = $this->actingAs($superUser)->get(route('roles.index'))->assertOk();

        $ids = $response->viewData('roles')->pluck('id');

        $this->assertTrue($ids->contains($this->rolDelSistema->id));
        $this->assertFalse($ids->contains($propio->id), 'Los roles de una barbería son suyos, no del catálogo.');
    }

    public function test_no_se_edita_el_rol_de_una_empresa_desde_el_catalogo_global(): void
    {
        $propio = Role::factory()->ownedBy($this->barberiaA)->create(['name' => 'Mi Supervisor']);
        $superUser = User::factory()->create(['is_super_admin' => true]);

        $this->actingAs($superUser)->get(route('roles.edit', $propio))->assertNotFound();
    }

    public function test_no_se_vacian_los_permisos_del_rol_del_operador(): void
    {
        $superAdmin = Role::factory()->create(['name' => 'Super Administrador', 'slug' => Role::SUPER_ADMIN]);
        $superAdmin->permissions()->sync([$this->permission('companies.view')->id]);

        $superUser = User::factory()->create(['is_super_admin' => true]);

        $this->actingAs($superUser)->put(route('roles.update', $superAdmin), [
            'name' => 'Super Administrador',
            'slug' => 'otro_slug',
            'permissions' => [],
        ])->assertRedirect(route('roles.index'));

        $superAdmin->refresh();

        $this->assertSame(Role::SUPER_ADMIN, $superAdmin->slug);
        $this->assertSame(['companies.view'], $superAdmin->permissions()->pluck('slug')->all());
    }

    /* ---------------------------------------------------------------------
     | Altas de usuarios
     |--------------------------------------------------------------------- */

    public function test_no_se_da_de_alta_un_usuario_en_una_empresa_ajena(): void
    {
        $admin = $this->userInCompany($this->barberiaA, ['users.create'], 'alta_a');

        $this->actingInCompany($admin, $this->barberiaA)
            ->post(route('users.store'), [
                'name' => 'infiltrado',
                'email' => 'infiltrado@test.test',
                'password' => 'Secreto@1234',
                'password_confirmation' => 'Secreto@1234',
                'company_id' => $this->barberiaB->id,
                'role_id' => $this->rolDelSistema->id,
            ])
            ->assertSessionHasErrors('company_id');

        $this->assertDatabaseMissing('users', ['email' => 'infiltrado@test.test']);
    }

    public function test_no_se_da_de_alta_un_usuario_como_super_admin_del_sistema(): void
    {
        $superAdminRole = Role::factory()->create(['name' => 'Super Administrador', 'slug' => Role::SUPER_ADMIN]);
        $admin = $this->userInCompany($this->barberiaA, ['users.create'], 'alta_a');

        $this->actingInCompany($admin, $this->barberiaA)
            ->post(route('users.store'), [
                'name' => 'aspirante',
                'email' => 'aspirante@test.test',
                'password' => 'Secreto@1234',
                'password_confirmation' => 'Secreto@1234',
                'is_super_admin' => 1,
                'company_id' => $this->barberiaA->id,
                'role_id' => $superAdminRole->id,
            ])
            ->assertSessionHasErrors('role_id');

        $this->assertDatabaseMissing('users', ['email' => 'aspirante@test.test']);
    }

    public function test_un_administrador_no_puede_marcar_a_nadie_como_super_admin(): void
    {
        $admin = $this->userInCompany($this->barberiaA, ['users.create'], 'alta_a');

        $this->actingInCompany($admin, $this->barberiaA)
            ->post(route('users.store'), [
                'name' => 'ayudante',
                'email' => 'ayudante@test.test',
                'password' => 'Secreto@1234',
                'password_confirmation' => 'Secreto@1234',
                'is_super_admin' => 1,
                'company_id' => $this->barberiaA->id,
                'role_id' => $this->rolDelSistema->id,
            ])
            ->assertRedirect(route('users.index'));

        $this->assertFalse((bool) User::where('email', 'ayudante@test.test')->firstOrFail()->is_super_admin);
    }

    public function test_no_se_asigna_el_rol_del_operador_a_un_usuario(): void
    {
        $superAdminRole = Role::factory()->create(['name' => 'Super Administrador', 'slug' => Role::SUPER_ADMIN]);
        $admin = $this->userInCompany($this->barberiaA, ['users.edit'], 'edita_a');
        $victima = $this->userInCompany($this->barberiaA, [], 'basico_a');

        $this->actingInCompany($admin, $this->barberiaA)
            ->post(route('users.assign-role', [$victima, $this->barberiaA, $superAdminRole]))
            ->assertForbidden();

        $this->assertDatabaseMissing('company_user', [
            'user_id' => $victima->id,
            'role_id' => $superAdminRole->id,
        ]);
    }

    public function test_no_se_asigna_un_rol_dentro_de_otra_empresa(): void
    {
        $admin = $this->userInCompany($this->barberiaA, ['users.edit'], 'edita_a');
        $ajeno = $this->userInCompany($this->barberiaB, [], 'basico_b');

        $this->actingInCompany($admin, $this->barberiaA)
            ->post(route('users.assign-role', [$ajeno, $this->barberiaB, $this->rolDelSistema]))
            ->assertForbidden();
    }

    /* ---------------------------------------------------------------------
     | Utilidades
     |--------------------------------------------------------------------- */

    protected function permission(string $slug): Permission
    {
        [$module] = explode('.', $slug);

        return Permission::firstOrCreate(['slug' => $slug], ['name' => $slug, 'module' => $module]);
    }

    protected function cargoEn(Company $company, string $name, Role $role): Cargo
    {
        return $this->inCompany($company, fn () => Cargo::create([
            'company_id' => $company->id,
            'role_id' => $role->id,
            'name' => $name,
            'active' => true,
        ]));
    }
}
