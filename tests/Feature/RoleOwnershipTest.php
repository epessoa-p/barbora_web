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
     | Cada cargo, su propio rol de la empresa
     |--------------------------------------------------------------------- */

    public function test_un_cargo_crea_su_propio_rol_en_la_empresa(): void
    {
        $this->actingInCompany($this->adminA, $this->barberiaA)
            ->post(route('cargos.store'), [
                'name' => 'Jefe de piso',
                'active' => 1,
                'permissions' => [$this->permission('appointments.view')->id],
            ])
            ->assertRedirect(route('cargos.index'));

        $cargo = Cargo::where('name', 'Jefe de piso')->firstOrFail();
        $rol = $cargo->role;

        $this->assertSame($this->barberiaA->id, $rol->company_id, 'El rol nace dentro de la empresa.');
        $this->assertFalse($rol->isSystem());
        $this->assertSame(['appointments.view'], $rol->permissions()->pluck('slug')->all());
    }

    public function test_el_formulario_de_cargo_no_deja_elegir_rol(): void
    {
        $response = $this->actingInCompany($this->adminA, $this->barberiaA)
            ->get(route('cargos.create'))
            ->assertOk();

        $response->assertSee('Datos del cargo');
        $response->assertSee('Permisos del rol');
        // Ya no hay selección de rol: cada cargo crea el suyo.
        $response->assertDontSee('Rol existente');
        $response->assertDontSee('name="role_id"', false);
    }

    /**
     * El agujero original: antes, elegir un rol del sistema y marcar permisos
     * reescribía ese rol para TODAS las empresas. Ahora el cargo crea su propio
     * rol y el del sistema no se toca, aunque se llamen igual.
     */
    public function test_un_cargo_no_toca_el_rol_del_sistema_aunque_se_llame_igual(): void
    {
        $this->actingInCompany($this->adminA, $this->barberiaA)
            ->post(route('cargos.store'), [
                'name' => 'Barbero', // mismo nombre que el rol del sistema
                'active' => 1,
                'permissions' => [$this->permission('sales.create')->id],
            ])
            ->assertRedirect(route('cargos.index'));

        $rolDelCargo = Cargo::where('name', 'Barbero')->firstOrFail()->role;

        // El rol del cargo es propio de la empresa, no el compartido.
        $this->assertSame($this->barberiaA->id, $rolDelCargo->company_id);
        $this->assertNotSame($this->rolDelSistema->id, $rolDelCargo->id);

        // Y el rol del sistema sigue exactamente igual para todo el SaaS.
        $this->assertSame(
            ['appointments.view'],
            $this->rolDelSistema->permissions()->pluck('slug')->all()
        );
    }

    public function test_al_editar_un_cargo_se_editan_los_permisos_de_su_rol_propio(): void
    {
        $rolPropio = Role::factory()->ownedBy($this->barberiaA)->create(['name' => 'Supervisor']);
        $cargo = $this->cargoEn($this->barberiaA, 'Supervisor', $rolPropio);
        $sales = $this->permission('sales.create');

        $this->actingInCompany($this->adminA, $this->barberiaA)
            ->put(route('cargos.update', $cargo), [
                'name' => 'Supervisor',
                'active' => 1,
                'permissions' => [$sales->id],
            ])
            ->assertRedirect(route('cargos.index'));

        $cargo->refresh();

        // Se editó el MISMO rol (no se creó otro) y quedó con el permiso nuevo.
        $this->assertSame($rolPropio->id, $cargo->role_id);
        $this->assertSame(['sales.create'], $rolPropio->permissions()->pluck('slug')->all());
    }

    /**
     * Un cargo heredado (datos antiguos) que apunta a un rol del sistema: al
     * editarlo se le crea un rol propio y se desengancha, sin tocar el global.
     */
    public function test_al_editar_un_cargo_heredado_del_sistema_se_le_crea_un_rol_propio(): void
    {
        $cargo = $this->cargoEn($this->barberiaA, 'Barbero', $this->rolDelSistema);
        $sales = $this->permission('sales.create');

        $this->actingInCompany($this->adminA, $this->barberiaA)
            ->put(route('cargos.update', $cargo), [
                'name' => 'Barbero',
                'active' => 1,
                'permissions' => [$sales->id],
            ])
            ->assertRedirect(route('cargos.index'));

        $cargo->refresh();

        // Ya no apunta al rol del sistema, sino a uno propio con el permiso nuevo.
        $this->assertNotSame($this->rolDelSistema->id, $cargo->role_id);
        $this->assertSame($this->barberiaA->id, $cargo->role->company_id);
        $this->assertSame(['sales.create'], $cargo->role->permissions()->pluck('slug')->all());

        // El rol del sistema, intacto para el resto del SaaS.
        $this->assertSame(
            ['appointments.view'],
            $this->rolDelSistema->permissions()->pluck('slug')->all()
        );
    }

    /* ---------------------------------------------------------------------
     | Escalada de privilegios
     |--------------------------------------------------------------------- */

    public function test_no_se_conceden_permisos_de_plataforma_desde_un_cargo(): void
    {
        $platform = $this->permission('companies.create');
        $legit = $this->permission('appointments.view');

        $this->actingInCompany($this->adminA, $this->barberiaA)
            ->post(route('cargos.store'), [
                'name' => 'Supervisor',
                'active' => 1,
                'permissions' => [$platform->id, $legit->id],
            ])
            ->assertRedirect(route('cargos.index'));

        $rol = Cargo::where('name', 'Supervisor')->firstOrFail()->role;

        $this->assertSame(
            ['appointments.view'],
            $rol->permissions()->pluck('slug')->all(),
            'Los permisos del operador no se conceden desde un cargo.'
        );
    }

    /* ---------------------------------------------------------------------
     | Aislamiento entre empresas
     |--------------------------------------------------------------------- */

    public function test_dos_empresas_pueden_llamar_igual_a_su_cargo(): void
    {
        $adminB = $this->userInCompany($this->barberiaB, ['cargos.create'], 'admin_b');

        foreach ([[$this->adminA, $this->barberiaA], [$adminB, $this->barberiaB]] as [$user, $company]) {
            $this->actingInCompany($user, $company)
                ->post(route('cargos.store'), [
                    'name' => 'Supervisor',
                    'active' => 1,
                ])
                ->assertRedirect(route('cargos.index'));
        }

        // Cada empresa tiene su cargo y su rol propio; el slug no compite entre ellas.
        $roles = Cargo::allCompanies()->where('name', 'Supervisor')->with('role')->get()
            ->pluck('role');

        $this->assertCount(2, $roles);
        $this->assertSame(
            [$this->barberiaA->id, $this->barberiaB->id],
            $roles->pluck('company_id')->sort()->values()->all()
        );
        $this->assertSame(['supervisor', 'supervisor'], $roles->pluck('slug')->all());
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

    public function test_una_empresa_no_da_de_alta_usuarios_desde_la_pantalla_de_usuarios(): void
    {
        // La pantalla de Usuarios es solo del superadmin. Un admin de empresa
        // no llega a ella (403), así que no hay vector de alta ni de escalada:
        // su gente la crea desde Personal, con el rol que fija el cargo.
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
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'infiltrado@test.test']);
    }

    public function test_una_empresa_no_asigna_roles_desde_la_pantalla_de_usuarios(): void
    {
        $superAdminRole = Role::factory()->create(['name' => 'Super Administrador', 'slug' => Role::SUPER_ADMIN]);
        $admin = $this->userInCompany($this->barberiaA, ['users.create', 'users.edit'], 'alta_a');

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
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'aspirante@test.test']);
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
