<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Company;
use App\Support\Tenancy;
use Illuminate\Database\Seeder;

/**
 * Dos barberías a propósito: con una sola no se puede comprobar a mano que el
 * aislamiento por empresa funciona de verdad.
 *
 * Cada una arranca con su local: sin sucursal no hay almacén —lo crea el
 * BranchObserver— y el inventario se quedaría sin dónde guardar nada.
 */
class CompanySeeder extends Seeder
{
    public function run(): void
    {
        $companies = [
            [
                'tax_id' => '1023456789',
                'name' => 'Barbería Demo',
                'tax_id_label' => 'NIT',
                'country' => 'BO',
                'currency' => 'BOB',
                'timezone' => 'America/La_Paz',
                'address' => 'Av. Ballivián 1234, Santa Cruz',
                'phone' => '+591 3 123-4567',
                'email' => 'hola@barberiademo.test',
                'description' => 'Barbería de demostración con el plan Premium.',
                'active' => true,
            ],
            [
                'tax_id' => '9876543210',
                'name' => 'Barbería Prueba',
                'tax_id_label' => 'NIT',
                'country' => 'BO',
                'currency' => 'BOB',
                'timezone' => 'America/La_Paz',
                'address' => 'Calle Comercio 456, La Paz',
                'phone' => '+591 2 765-4321',
                'email' => 'hola@barberiaprueba.test',
                'description' => 'Segunda empresa, para verificar el aislamiento entre tenants.',
                'active' => true,
            ],
        ];

        foreach ($companies as $company) {
            Company::updateOrCreate(['tax_id' => $company['tax_id']], $company);
        }

        $branches = [
            '1023456789' => [
                ['name' => 'Sucursal Centro', 'code' => 'SUC-01', 'phone' => '+591 3 123-4567', 'address' => 'Av. Ballivián 1234'],
                ['name' => 'Sucursal Norte',  'code' => 'SUC-02', 'phone' => '+591 3 765-4321', 'address' => 'Av. Banzer km 6'],
            ],
            '9876543210' => [
                ['name' => 'Local Comercio', 'code' => 'SUC-01', 'phone' => '+591 2 765-4321', 'address' => 'Calle Comercio 456'],
            ],
        ];

        foreach ($branches as $taxId => $rows) {
            $company = Company::where('tax_id', $taxId)->first();

            if (! $company) {
                continue;
            }

            app(Tenancy::class)->runFor($company->id, function () use ($rows) {
                foreach ($rows as $branch) {
                    Branch::firstOrCreate(['name' => $branch['name']], $branch + ['active' => true]);
                }
            });
        }
    }
}
