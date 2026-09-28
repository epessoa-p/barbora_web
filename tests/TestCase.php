<?php

namespace Tests;

use App\Models\Company;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\Role;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // El singleton arranca cerrado en tests (ver AppServiceProvider): así
        // los tests de aislamiento comprueban el comportamiento real y no uno
        // relajado por estar en consola.
        app(Tenancy::class)->forget();
    }

    /**
     * Crea una empresa con suscripción activa a un plan sin límites.
     *
     * @param  array<string, mixed>  $companyAttributes
     * @param  array<string, mixed>  $subscriptionAttributes
     */
    protected function companyWithPlan(
        array $companyAttributes = [],
        array $planAttributes = [],
        array $subscriptionAttributes = [],
    ): Company {
        $company = Company::factory()->create($companyAttributes);
        $plan = Plan::factory()->create($planAttributes);

        $company->subscription()->create(array_merge([
            'plan_id' => $plan->id,
            'status' => 'active',
            'current_period_end' => now()->addMonth(),
            'grace_days' => 3,
        ], $subscriptionAttributes));

        return $company->refresh();
    }

    /**
     * Crea un usuario con un rol en una empresa, y le otorga los permisos dados.
     *
     * @param  array<int, string>  $permissions
     */
    protected function userInCompany(Company $company, array $permissions = [], string $roleSlug = 'tester'): User
    {
        $role = Role::firstOrCreate(
            ['company_id' => null, 'slug' => $roleSlug],
            ['name' => ucfirst($roleSlug), 'description' => 'Rol de prueba']
        );

        $ids = collect($permissions)->map(function (string $slug) {
            [$module] = explode('.', $slug);

            return Permission::firstOrCreate(
                ['slug' => $slug],
                ['name' => $slug, 'module' => $module]
            )->id;
        });

        $role->permissions()->syncWithoutDetaching($ids);

        $user = User::factory()->create();
        $user->companies()->attach($company->id, ['role_id' => $role->id, 'active' => true]);

        return $user;
    }

    /** Inicia sesión como el usuario y fija su empresa activa. */
    protected function actingInCompany(User $user, Company $company): static
    {
        return $this->actingAs($user)->withSession(['current_company_id' => $company->id]);
    }

    /**
     * Ejecuta el callback con esa empresa como activa.
     *
     * Hace falta al tocar modelos directamente, sin pasar por una petición: en
     * tests el Tenancy arranca cerrado (ver AppServiceProvider), así que sin
     * esto el CompanyScope devuelve vacío — que es justo lo que debe hacer.
     */
    protected function inCompany(Company $company, callable $callback): mixed
    {
        return app(Tenancy::class)->runFor($company->id, $callback);
    }
}
