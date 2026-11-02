<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\Personal;
use Illuminate\Validation\Rule;

/**
 * Reglas de una caja registradora, compartidas por la web y la API.
 *
 * Toda caja es de una sucursal de la misma empresa: sus turnos se numeran ahí
 * (T-000001…) y las ventas de esa sucursal cobran en ella. El responsable, si
 * lo hay, trabaja en esa misma sucursal.
 */
class CajaForm
{
    public static function rules(?int $companyId): array
    {
        return [
            'name'        => 'required|string|max:255',
            'code'        => 'nullable|string|max:50',
            'branch_id'   => [
                $companyId && Branch::forCompany($companyId)->exists() ? 'required' : 'nullable',
                Rule::exists('branches', 'id')->where('company_id', $companyId),
            ],
            'personal_id' => [
                'nullable',
                Rule::exists('personal', 'id')->where('company_id', $companyId),
                function (string $attribute, mixed $value, \Closure $fail) {
                    $personal = Personal::withoutGlobalScopes()->find($value);

                    if ($personal?->branch_id && (int) $personal->branch_id !== (int) request('branch_id')) {
                        $fail("{$personal->full_name} trabaja en otra sucursal: asígnale una caja de la suya.");
                    }
                },
            ],
            'description' => 'nullable|string',
            'active'      => 'boolean',
        ];
    }
}
