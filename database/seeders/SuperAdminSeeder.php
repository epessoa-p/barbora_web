<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Usuarios de demostración y su pertenencia a empresas.
 *
 * La asignación rol → permiso vive en RolePermissionSeeder.
 */
class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        // Operador de la plataforma: sin empresa, ve y puede todo.
        User::updateOrCreate(
            ['email' => 'superadmin@barbora.test'],
            [
                'name' => 'Super Administrador',
                'password' => 'Admin@1234',
                'is_super_admin' => true,
                'active' => true,
            ]
        );

        $demo = Company::where('tax_id', '1023456789')->first();
        $prueba = Company::where('tax_id', '9876543210')->first();

        $members = [
            // email, nombre, empresa, rol
            ['admin@barberiademo.test', 'Admin Barbería Demo', $demo, 'admin'],
            // Con permisos recortados: sirve para comprobar que el menú y las
            // rutas se filtran de verdad.
            ['cajero@barberiademo.test', 'Cajero Barbería Demo', $demo, 'cashier'],
            // En la otra empresa: sirve para comprobar el aislamiento.
            ['admin@barberiaprueba.test', 'Admin Barbería Prueba', $prueba, 'admin'],
        ];

        foreach ($members as [$email, $name, $company, $roleSlug]) {
            if (! $company) {
                continue;
            }

            $role = Role::system()->where('slug', $roleSlug)->first();

            if (! $role) {
                $this->command?->warn("SuperAdminSeeder: no existe el rol «{$roleSlug}», se omite {$email}.");
                continue;
            }

            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => 'Admin@1234',
                    'is_super_admin' => false,
                    'active' => true,
                ]
            );

            $user->companies()->syncWithoutDetaching([
                $company->id => ['role_id' => $role->id, 'active' => true],
            ]);
        }
    }
}
