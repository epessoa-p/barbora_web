<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

abstract class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    /**
     * Empresa sobre la que actúa la petición: la activa, o la que indique el
     * superadmin por parámetro cuando trabaja en Modo Global.
     */
    protected function targetCompanyId(): ?int
    {
        $user = auth()->user();

        if ($user?->is_super_admin) {
            return request('company_id')
                ? (int) request('company_id')
                : request()->attributes->get('tenant_company')?->id;
        }

        return request()->attributes->get('tenant_company')?->id
            ?? $user?->getCurrentCompany()?->id;
    }

    /**
     * Cuarta capa de autorización: ¿queda cupo en el plan?
     * Solo se comprueba al CREAR un recurso limitado. Ver ARQUITECTURA §9.
     *
     * @param  string  $key  'users' | 'branches' | 'products'
     */
    protected function planLimitReached(?int $companyId, string $key): bool
    {
        if (! $companyId || auth()->user()?->is_super_admin) {
            return false;
        }

        $company = Company::find($companyId);

        return $company && ! $company->withinLimit($key);
    }

    /**
     * Estado del cupo, para pintar o deshabilitar el botón "Crear".
     *
     * @return array{reached: bool, usage: int, max: ?int, unlimited: bool}
     */
    protected function planLimitStatus(?int $companyId, string $key): array
    {
        $empty = ['reached' => false, 'usage' => 0, 'max' => null, 'unlimited' => true];

        if (! $companyId || auth()->user()?->is_super_admin) {
            return $empty;
        }

        $company = Company::find($companyId);

        if (! $company) {
            return $empty;
        }

        $max = $company->effectiveLimit($key);
        $usage = $company->usageFor($key);

        return [
            'reached' => $max !== null && $usage >= $max,
            'usage' => $usage,
            'max' => $max,
            'unlimited' => $max === null,
        ];
    }
}
