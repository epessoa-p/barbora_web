<?php

namespace App\Models\Concerns;

use App\Support\DocumentNumber;
use Illuminate\Database\Eloquent\Model;

/**
 * Da al documento su número correlativo de sucursal al crearlo.
 *
 * El modelo declara NUMBER_TYPE (V, C, T, I) y puede sobrescribir
 * resolveNumberBranch() para deducir su sucursal (la de su caja, su almacén…).
 * Va después de BelongsToCompany en el `use`, que es quien pone company_id.
 */
trait HasBranchNumber
{
    public static function bootHasBranchNumber(): void
    {
        static::creating(function (Model $model) {
            $model->resolveNumberBranch();

            if (blank($model->number) && $model->company_id) {
                $model->number = DocumentNumber::next(
                    (int) $model->company_id,
                    $model->branch_id ? (int) $model->branch_id : null,
                    static::NUMBER_TYPE,
                    fn () => static::lastUsedSequence((int) $model->company_id, $model->branch_id),
                );
            }
        });
    }

    /** Hook: completa branch_id antes de numerar. */
    protected function resolveNumberBranch(): void
    {
    }

    /** El mayor número ya guardado en esa sucursal, para arrancar el contador. */
    protected static function lastUsedSequence(int $companyId, ?int $branchId): int
    {
        return (int) static::query()->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId), fn ($q) => $q->whereNull('branch_id'))
            ->where('number', 'like', static::NUMBER_TYPE.'-%')
            ->pluck('number')
            ->map(fn ($n) => DocumentNumber::sequenceOf($n))
            ->max();
    }
}
