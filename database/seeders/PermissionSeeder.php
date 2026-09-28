<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

/**
 * Permisos granulares, con slug `modulo.accion`.
 *
 * Todos los módulos de aquí son ADMINISTRATIVOS: no figuran en
 * Plan::PERMISSION_MODULE_FEATURES, así que están siempre disponibles y no
 * dependen del plan contratado. Ver ARQUITECTURA §6.2.
 */
class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $modules = [
            'companies' => ['label' => 'Empresas', 'article' => 'las empresas'],
            'plans' => ['label' => 'Planes', 'article' => 'los planes'],
            'subscriptions' => ['label' => 'Suscripciones', 'article' => 'las suscripciones', 'actions' => ['view', 'edit']],
            'users' => ['label' => 'Usuarios', 'article' => 'los usuarios'],
            'roles' => ['label' => 'Roles', 'article' => 'los roles'],
            'cargos' => ['label' => 'Cargos', 'article' => 'los cargos'],
            'personal' => ['label' => 'Personal', 'article' => 'el personal'],
            'schedules' => ['label' => 'Horarios', 'article' => 'los horarios de trabajo', 'actions' => ['view', 'edit']],
            'branches' => ['label' => 'Sucursales', 'article' => 'las sucursales'],
            'appointments' => ['label' => 'Citas', 'article' => 'las citas'],
            'services' => ['label' => 'Servicios', 'article' => 'los servicios'],
            'service_categories' => ['label' => 'Categorías de servicio', 'article' => 'las categorías de servicio'],
            'clients' => ['label' => 'Clientes', 'article' => 'los clientes'],
            'warehouses' => ['label' => 'Almacenes', 'article' => 'los almacenes'],
            'products' => ['label' => 'Productos', 'article' => 'los productos'],
            'product_categories' => ['label' => 'Categorías de producto', 'article' => 'las categorías de producto'],
            'stock' => ['label' => 'Existencias', 'article' => 'las existencias y sus movimientos', 'actions' => ['view', 'create']],
            'sales' => ['label' => 'Ventas', 'article' => 'las ventas'],
            'commissions' => ['label' => 'Comisiones', 'article' => 'las comisiones y sus liquidaciones', 'actions' => ['view', 'create']],
            'commission_rules' => ['label' => 'Reglas de comisión', 'article' => 'las reglas de comisión'],
            'cajas' => ['label' => 'Cajas', 'article' => 'las cajas'],
            'cash' => ['label' => 'Turnos de caja', 'article' => 'los turnos de caja y sus movimientos'],
            'reports' => ['label' => 'Reportes', 'article' => 'los reportes del negocio', 'actions' => ['view', 'export']],
            'agenda_blocks' => ['label' => 'Bloqueos de agenda', 'article' => 'los bloqueos de agenda (vacaciones, feriados)'],
            'settings' => ['label' => 'Configuración', 'article' => 'la configuración de la barbería', 'actions' => ['view', 'edit']],
        ];

        $verbs = [
            'view' => ['Ver', 'Consultar'],
            'create' => ['Crear', 'Dar de alta'],
            'edit' => ['Editar', 'Modificar'],
            'delete' => ['Eliminar', 'Dar de baja'],
            'export' => ['Exportar', 'Descargar'],
        ];

        // Las acciones por defecto van escritas, NO son array_keys($verbs): así
        // añadir un verbo nuevo (como «export») no se lo cuelga de golpe a los
        // veinte módulos que no declaran los suyos.
        $defaultActions = ['view', 'create', 'edit', 'delete'];

        foreach ($modules as $module => $meta) {
            foreach ($meta['actions'] ?? $defaultActions as $action) {
                [$verb, $description] = $verbs[$action];

                Permission::updateOrCreate(
                    ['slug' => "{$module}.{$action}"],
                    [
                        'name' => "{$verb} {$meta['label']}",
                        'module' => $module,
                        'description' => "{$description} {$meta['article']}",
                    ]
                );
            }
        }
    }
}
