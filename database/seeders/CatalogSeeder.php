<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Company;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Support\Tenancy;
use Illuminate\Database\Seeder;

/**
 * Catálogo y clientes de ejemplo para la Barbería Demo.
 *
 * Solo la empresa Demo: Barbería Prueba se deja vacía a propósito, para poder
 * comprobar a simple vista que el aislamiento entre empresas funciona.
 */
class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $demo = Company::where('tax_id', '1023456789')->first();

        if (! $demo) {
            return;
        }

        app(Tenancy::class)->runFor($demo->id, function () {
            $categories = [
                ['name' => 'Corte',        'color' => '#2563eb', 'sort_order' => 1],
                ['name' => 'Barba',        'color' => '#b45309', 'sort_order' => 2],
                ['name' => 'Color',        'color' => '#7c3aed', 'sort_order' => 3],
                ['name' => 'Tratamientos', 'color' => '#0d9488', 'sort_order' => 4],
            ];

            $ids = [];

            foreach ($categories as $category) {
                $ids[$category['name']] = ServiceCategory::updateOrCreate(
                    ['name' => $category['name']],
                    $category + ['active' => true]
                )->id;
            }

            $services = [
                ['Corte clásico',        'Corte', 30,  50.00, 'Lavado, corte y peinado'],
                ['Corte + barba',        'Corte', 50,  80.00, 'El combo de siempre'],
                ['Corte infantil',       'Corte', 25,  40.00, 'Hasta 12 años'],
                ['Perfilado de barba',   'Barba', 20,  35.00, 'Navaja y toalla caliente'],
                ['Afeitado clásico',     'Barba', 30,  45.00, 'Al ras, con navaja'],
                ['Tinte',                'Color', 60, 120.00, null],
                ['Decoloración',         'Color', 90, 180.00, null],
                ['Tratamiento capilar',  'Tratamientos', 40, 90.00, 'Hidratación profunda'],
            ];

            foreach ($services as $i => [$name, $category, $minutes, $price, $description]) {
                Service::updateOrCreate(['name' => $name], [
                    'service_category_id' => $ids[$category],
                    'description' => $description,
                    'duration_minutes' => $minutes,
                    'price' => $price,
                    'sort_order' => $i + 1,
                    'active' => true,
                ]);
            }

            $clients = [
                ['Carlos Mendoza',  '+591 7 111-2233', '1234567', now()->subYears(32)->startOfMonth(), 'Máquina 2 a los costados', null],
                ['Diego Rojas',     '+591 7 222-3344', '2345678', now()->subYears(28)->subMonths(3), 'Raya al costado', 'Tintes con amoníaco'],
                ['Andrés Vargas',   '+591 7 333-4455', null, null, 'Barba recortada, no al ras', null],
                ['Martín Quiroga',  '+591 7 444-5566', '4567890', now()->subYears(41)->subMonths(7), null, null],
            ];

            foreach ($clients as [$name, $phone, $document, $birthDate, $preferences, $allergies]) {
                Client::updateOrCreate(['full_name' => $name], [
                    'phone' => $phone,
                    'document_number' => $document,
                    'birth_date' => $birthDate,
                    'preferences' => $preferences,
                    'allergies' => $allergies,
                    'active' => true,
                ]);
            }
        });
    }
}
