<?php

namespace App\Observers;

use App\Models\Company;
use App\Models\PaymentMethod;
use App\Models\Scopes\CompanyScope;

/**
 * Toda barbería nace con sus métodos de pago.
 *
 * Vive en un observer, como el almacén de las sucursales, para que la regla se
 * cumpla venga la empresa de donde venga: el panel del operador, un seeder, la
 * consola o un test. Sin esto, una barbería recién creada no podría cobrar
 * porque el desplegable de métodos saldría vacío.
 */
class CompanyObserver
{
    public function created(Company $company): void
    {
        foreach (PaymentMethod::DEFAULTS as $index => $method) {
            // Sin el CompanyScope: al crear la empresa todavía no es la activa,
            // y el scope está en fail-closed.
            PaymentMethod::withoutGlobalScope(CompanyScope::class)->create([
                'company_id' => $company->id,
                'name' => $method['name'],
                'slug' => $method['slug'],
                'counts_as_cash' => $method['counts_as_cash'],
                'requires_reference' => $method['requires_reference'],
                'active' => true,
                'sort_order' => $index,
            ]);
        }
    }
}
