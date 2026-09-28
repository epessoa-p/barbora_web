<?php

namespace App\Models\Scopes;

use App\Support\Tenancy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Filtra automáticamente por la empresa activa todo modelo que use
 * el trait BelongsToCompany. Ver ARQUITECTURA §4.2.
 */
class CompanyScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $tenancy = app(Tenancy::class);

        // Modo Global (superadmin, consola): sin filtro.
        if ($tenancy->isUnrestricted()) {
            return;
        }

        $companyId = $tenancy->id();

        // Fail-closed: sin empresa activa no se ve NADA. Nunca "todo".
        if ($companyId === null) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->where($model->qualifyColumn('company_id'), $companyId);
    }
}
