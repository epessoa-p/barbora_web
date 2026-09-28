<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Catálogo global de roles. Los slugs van en inglés; los nombres y
 * descripciones, en el lenguaje de una barbería.
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'name' => 'Super Administrador',
                'slug' => 'super_admin',
                'description' => 'Operador de la plataforma Barbora',
            ],
            [
                'name' => 'Administrador',
                'slug' => 'admin',
                'description' => 'Administra toda la empresa: sucursales, personal y configuración',
            ],
            [
                'name' => 'Encargado de sucursal',
                'slug' => 'manager',
                'description' => 'Gestiona la operación diaria de una sucursal',
            ],
            [
                'name' => 'Cajero',
                'slug' => 'cashier',
                'description' => 'Cobra, abre y cierra caja',
            ],
            [
                'name' => 'Recepcionista',
                'slug' => 'receptionist',
                'description' => 'Agenda citas y atiende a los clientes',
            ],
            [
                'name' => 'Barbero',
                'slug' => 'barber',
                'description' => 'Atiende clientes y consulta su agenda',
            ],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(['company_id' => null, 'slug' => $role['slug']], $role);
        }
    }
}
