<?php

namespace Database\Seeders;

use App\Support\Tenancy;
use Illuminate\Database\Seeder;

/**
 * Ojo: aquí NO se usa WithoutModelEvents. Barbora apoya reglas de negocio en
 * los eventos de modelo — BelongsToCompany rellena `company_id` al crear y
 * BranchObserver levanta el almacén espejo de cada sucursal—, así que
 * desactivarlos dejaría los datos sembrados a medio construir.
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Sin esto, cualquier modelo tenant que se siembre quedaría filtrado
        // por una empresa activa que aquí no existe (y en tests, vacío).
        app(Tenancy::class)->runUnrestricted(function () {
            // El orden importa: los permisos antes de asignarlos, los planes
            // antes de las suscripciones, las empresas antes de sus usuarios.
            $this->call([
                RoleSeeder::class,
                PermissionSeeder::class,
                RolePermissionSeeder::class,
                PlanSeeder::class,
                CompanySeeder::class,
                SubscriptionSeeder::class,
                SuperAdminSeeder::class,
                CatalogSeeder::class,
                StaffSeeder::class,
                AppointmentSeeder::class,
                CashSeeder::class,
                InventorySeeder::class,
                CommissionSeeder::class,
                SaleSeeder::class,
            ]);
        });
    }
}
