<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WarehouseController extends Controller
{
    public function index()
    {
        $query = Warehouse::with('branch')->orderBy('code');

        if (auth()->user()->is_super_admin && request('company_id')) {
            $query->forCompany((int) request('company_id'));
        }

        return view('admin.warehouses.index', ['warehouses' => $query->paginate(15)]);
    }

    public function create()
    {
        return view('admin.warehouses.create', ['warehouse' => null]);
    }

    public function store(Request $request)
    {
        $companyId = $this->targetCompanyId();

        $data = $request->validate($this->rules($companyId));

        $data['company_id'] = $companyId;
        $data['branch_id'] = null;          // los de sucursal los crea el observer
        $data['code'] = Warehouse::nextCodeFor((int) $companyId);
        $data['active'] = $request->boolean('active', true);

        $warehouse = Warehouse::create($data);

        return redirect()->route('warehouses.index')
            ->with('success', "AlmacÃ©n Â«{$warehouse->name}Â» creado con el cÃ³digo {$warehouse->code}.");
    }

    public function show(Warehouse $warehouse)
    {
        $this->authorizeWarehouse($warehouse);
        $warehouse->load('branch');

        return view('admin.warehouses.show', compact('warehouse'));
    }

    public function edit(Warehouse $warehouse)
    {
        $this->authorizeWarehouse($warehouse);

        if ($response = $this->denyIfManagedByBranch($warehouse)) {
            return $response;
        }

        return view('admin.warehouses.edit', compact('warehouse'));
    }

    public function update(Request $request, Warehouse $warehouse)
    {
        $this->authorizeWarehouse($warehouse);

        if ($response = $this->denyIfManagedByBranch($warehouse)) {
            return $response;
        }

        $data = $request->validate($this->rules($warehouse->company_id, $warehouse));
        $data['active'] = $request->boolean('active', false);

        $warehouse->update($data);

        return redirect()->route('warehouses.index')
            ->with('success', 'AlmacÃ©n actualizado exitosamente.');
    }

    public function destroy(Warehouse $warehouse)
    {
        $this->authorizeWarehouse($warehouse);

        if ($response = $this->denyIfManagedByBranch($warehouse)) {
            return $response;
        }

        $warehouse->delete();

        return redirect()->route('warehouses.index')
            ->with('success', 'AlmacÃ©n eliminado exitosamente.');
    }

    /**
     * El cÃ³digo lo genera el sistema, asÃ­ que nunca se valida desde el formulario.
     */
    protected function rules(?int $companyId, ?Warehouse $warehouse = null): array
    {
        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('warehouses', 'name')
                    ->where(fn ($q) => $q->where('company_id', $companyId))
                    ->ignore($warehouse?->id),
            ],
            'phone' => 'nullable|string|max:30',
            'address' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'active' => 'sometimes|boolean',
        ];
    }

    protected function authorizeWarehouse(Warehouse $warehouse): void
    {
        $user = auth()->user();

        if (! $user->is_super_admin && $warehouse->company_id !== $user->getCurrentCompany()?->id) {
            abort(403);
        }
    }

    /**
     * Un almacÃ©n ligado a una sucursal es un espejo de ella: se edita cambiando
     * la sucursal, nunca desde aquÃ­.
     */
    protected function denyIfManagedByBranch(Warehouse $warehouse)
    {
        if (! $warehouse->isManagedByBranch()) {
            return null;
        }

        return redirect()->route('warehouses.index')->withErrors([
            'error' => "El almacÃ©n Â«{$warehouse->name}Â» pertenece a la sucursal "
                     . "Â«{$warehouse->branch?->name}Â». Para cambiarlo, edita la sucursal.",
        ]);
    }
}
