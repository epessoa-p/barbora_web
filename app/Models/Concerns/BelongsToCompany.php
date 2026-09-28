<?php

namespace App\Models\Concerns;

use App\Models\Company;
use App\Models\Scopes\CompanyScope;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Marca un modelo como dato de una empresa (tenant).
 *
 * Aplica el filtro por company_id a toda consulta y lo autocompleta al crear,
 * de forma que el código de negocio nunca escribe where('company_id', …).
 * Ver ARQUITECTURA §4.
 *
 * NO lo usan los modelos globales: User, Company, Role, Permission, Plan,
 * Subscription.
 */
trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        static::addGlobalScope(new CompanyScope);

        static::creating(function (Model $model) {
            if (empty($model->company_id)) {
                $model->company_id = app(Tenancy::class)->id();
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Salta el scope y filtra por una empresa concreta.
     * Imprescindible al calcular cupos o al listar desde el panel del operador.
     */
    public function scopeForCompany(Builder $query, Company|int|null $company): Builder
    {
        $companyId = $company instanceof Company ? $company->id : $company;

        return $query->withoutGlobalScope(CompanyScope::class)
                     ->where($this->qualifyColumn('company_id'), $companyId);
    }

    /**
     * Salta el scope por completo (listados globales del superadmin).
     */
    public function scopeAllCompanies(Builder $query): Builder
    {
        return $query->withoutGlobalScope(CompanyScope::class);
    }
}
