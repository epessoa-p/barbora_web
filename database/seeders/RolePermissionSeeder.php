<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Qué permisos otorga cada rol.
 *
 * Va aparte del alta de usuarios a propósito: cuando esta asignación vivía
 * dentro de SuperAdminSeeder, referenciaba slugs inexistentes y tres roles
 * quedaron en silencio sin ningún permiso. Por eso al final se avisa de
 * cualquier slug que no exista en la base de datos.
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $matrix = [
            'admin' => [
                'companies.view',
                'users.view', 'users.create', 'users.edit', 'users.delete',
                'cargos.view', 'cargos.create', 'cargos.edit', 'cargos.delete',
                'personal.view', 'personal.create', 'personal.edit', 'personal.delete',
                'appointments.view', 'appointments.create', 'appointments.edit', 'appointments.delete',
                'schedules.view', 'schedules.edit',
                'agenda_blocks.view', 'agenda_blocks.create', 'agenda_blocks.edit', 'agenda_blocks.delete',
                'branches.view', 'branches.create', 'branches.edit', 'branches.delete',
                'services.view', 'services.create', 'services.edit', 'services.delete',
                'service_categories.view', 'service_categories.create', 'service_categories.edit', 'service_categories.delete',
                'clients.view', 'clients.create', 'clients.edit', 'clients.delete',
                'warehouses.view', 'warehouses.create', 'warehouses.edit', 'warehouses.delete',
                'products.view', 'products.create', 'products.edit', 'products.delete',
                'product_categories.view', 'product_categories.create', 'product_categories.edit', 'product_categories.delete',
                'stock.view', 'stock.create',
                'commissions.view', 'commissions.create',
                'commission_rules.view', 'commission_rules.create', 'commission_rules.edit', 'commission_rules.delete',
                'sales.view', 'sales.create', 'sales.delete',
                'cajas.view', 'cajas.create', 'cajas.edit', 'cajas.delete',
                'cash.view', 'cash.create', 'cash.edit', 'cash.delete',
                'reports.view', 'reports.export',
                'settings.view', 'settings.edit',
                'subscriptions.view',
            ],
            'manager' => [
                'users.view',
                'cargos.view',
                'personal.view', 'personal.create', 'personal.edit',
                // Cuadrar turnos es trabajo de encargado.
                'schedules.view', 'schedules.edit',
                'appointments.view', 'appointments.create', 'appointments.edit', 'appointments.delete',
                // Aprueba vacaciones y descansos de su equipo, pero no los borra.
                'agenda_blocks.view', 'agenda_blocks.create', 'agenda_blocks.edit',
                'branches.view',
                'services.view', 'services.create', 'services.edit',
                'service_categories.view', 'service_categories.create', 'service_categories.edit',
                'clients.view', 'clients.create', 'clients.edit', 'clients.delete',
                'warehouses.view', 'warehouses.edit',
                'products.view', 'products.create', 'products.edit',
                'product_categories.view', 'product_categories.create', 'product_categories.edit',
                'stock.view', 'stock.create',
                'commissions.view', 'commissions.create',
                'commission_rules.view',
                'sales.view', 'sales.create', 'sales.delete',
                'cajas.view', 'cajas.create', 'cajas.edit',
                'cash.view', 'cash.create', 'cash.edit', 'cash.delete',
                // El encargado responde por los números de su sucursal.
                'reports.view', 'reports.export',
                // Consulta cómo está configurado, pero no lo cambia.
                'settings.view',
            ],
            'cashier' => [
                'branches.view',
                'personal.view',
                // Vende producto en mostrador: consulta stock, no lo mueve.
                'products.view', 'stock.view',
                'appointments.view', 'appointments.edit',
                'services.view',
                'clients.view', 'clients.create', 'clients.edit',
                // Cobra: registra ventas pero no las anula.
                'sales.view', 'sales.create',
                // Cajero: abre turno, mueve dinero y arquea; no anula.
                'cash.view', 'cash.create', 'cash.edit',
                'cajas.view',
            ],
            // Recepción vive del catálogo y de la ficha del cliente: es quien
            // da de alta y corrige los datos al atender por teléfono.
            'receptionist' => [
                'branches.view',
                'personal.view',
                // Solo consulta: necesita saber quién trabaja para agendar.
                'schedules.view',
                // Recepción es quien vive de la agenda.
                'appointments.view', 'appointments.create', 'appointments.edit', 'appointments.delete',
                // Ve los bloqueos para saber por qué un hueco está cerrado,
                // pero no declara feriados ni vacaciones.
                'agenda_blocks.view',
                'services.view', 'service_categories.view',
                'clients.view', 'clients.create', 'clients.edit',
                'sales.view', 'sales.create',
                // También cobra, así que necesita operar el turno de caja.
                'cash.view', 'cash.create', 'cash.edit',
                'cajas.view',
            ],
            'barber' => [
                'personal.view',
                // Para consultar su propio horario.
                'schedules.view',
                // Ve su agenda y marca la cita como atendida.
                'appointments.view', 'appointments.edit',
                // Consulta sus propias vacaciones y los feriados.
                'agenda_blocks.view',
                'sales.view',
                // Consulta lo que ha ganado.
                'commissions.view',
                'services.view',
                'clients.view',
            ],
        ];

        $missing = [];

        // El operador de la plataforma tiene todos los permisos.
        if ($superAdmin = Role::system()->where('slug', 'super_admin')->first()) {
            $superAdmin->permissions()->sync(Permission::pluck('id'));
        }

        foreach ($matrix as $roleSlug => $slugs) {
            $role = Role::system()->where('slug', $roleSlug)->first();

            if (! $role) {
                $missing[] = "rol «{$roleSlug}»";
                continue;
            }

            $ids = Permission::whereIn('slug', $slugs)->pluck('id', 'slug');

            foreach (array_diff($slugs, $ids->keys()->all()) as $slug) {
                $missing[] = "{$roleSlug} → {$slug}";
            }

            $role->permissions()->sync($ids->values());
        }

        if ($missing) {
            $this->command?->warn(
                'RolePermissionSeeder: estos permisos no existen y se ignoraron: '
                . implode(', ', $missing)
            );
        }
    }
}
