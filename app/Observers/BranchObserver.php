<?php

namespace App\Observers;

use App\Models\Branch;
use App\Models\Scopes\CompanyScope;
use App\Models\Warehouse;

/**
 * Cada sucursal tiene su almacén espejo.
 *
 * Vive en un observer y no en el controlador para que la regla se cumpla venga
 * la sucursal de donde venga: panel, seeder, consola o test.
 */
class BranchObserver
{
    public function created(Branch $branch): void
    {
        // Sin el CompanyScope: el superadmin puede crear sucursales en Modo
        // Global, donde no hay empresa activa de la que heredar company_id.
        Warehouse::withoutGlobalScope(CompanyScope::class)->create([
            'company_id' => $branch->company_id,
            'branch_id' => $branch->id,
            'name' => $branch->name,
            'code' => Warehouse::nextCodeFor((int) $branch->company_id),
            'phone' => $branch->phone,
            'address' => $branch->address,
            // El valor por defecto lo pone la base de datos, no el modelo: si
            // la sucursal se creó sin indicarlo, aquí todavía es null.
            'active' => $branch->active ?? true,
        ]);
    }

    /**
     * El almacén no se puede editar desde su CRUD, así que la sucursal es su
     * única fuente de verdad: si cambia, el almacén la sigue. El código no,
     * que lo genera el sistema una sola vez.
     */
    public function updated(Branch $branch): void
    {
        $warehouse = $this->warehouseOf($branch);

        $warehouse?->update([
            'name' => $branch->name,
            'phone' => $branch->phone,
            'address' => $branch->address,
            // El valor por defecto lo pone la base de datos, no el modelo: si
            // la sucursal se creó sin indicarlo, aquí todavía es null.
            'active' => $branch->active ?? true,
        ]);
    }

    public function deleted(Branch $branch): void
    {
        $this->warehouseOf($branch)?->delete();
    }

    public function restored(Branch $branch): void
    {
        Warehouse::withoutGlobalScope(CompanyScope::class)
            ->onlyTrashed()
            ->where('branch_id', $branch->id)
            ->first()?->restore();
    }

    /** Solo se salta el filtro por empresa; el de soft-delete sigue vigente. */
    protected function warehouseOf(Branch $branch): ?Warehouse
    {
        return Warehouse::withoutGlobalScope(CompanyScope::class)
            ->where('branch_id', $branch->id)
            ->first();
    }
}
