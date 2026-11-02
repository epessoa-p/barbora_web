<?php

namespace App\Http\Controllers\Purchases;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\Request;

/** Proveedores: a quién se le compra mercadería o se le paga un gasto. */
class SupplierController extends Controller
{
    public function index(Request $request)
    {
        $query = Supplier::withCount('purchases')->orderByDesc('active')->orderBy('name');

        if ($term = trim((string) $request->query('q'))) {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$term}%")
                ->orWhere('tax_id', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%"));
        }

        $suppliers = $query->paginate(25)->withQueryString();
        // La deuda de cada uno, en una sola consulta.
        $debts = \App\Models\Purchase::whereIn('supplier_id', $suppliers->pluck('id'))
            ->where('status', '!=', 'anulada')
            ->groupBy('supplier_id')
            ->selectRaw('supplier_id, SUM(total - paid) as debt')
            ->pluck('debt', 'supplier_id');

        return view('purchases.suppliers.index', compact('suppliers', 'debts'));
    }

    public function create()
    {
        return view('purchases.suppliers.create', ['supplier' => null]);
    }

    public function store(Request $request)
    {
        $supplier = Supplier::create($this->validated($request) + [
            'company_id' => $this->targetCompanyId(),
            'active' => true,
        ]);

        return redirect()->route('suppliers.show', $supplier)->with('success', "Proveedor «{$supplier->name}» registrado.");
    }

    /** Alta rápida desde una compra o un gasto (modal): responde JSON. */
    public function quickStore(Request $request)
    {
        $supplier = Supplier::create($this->validated($request) + [
            'company_id' => $this->targetCompanyId(),
            'active' => true,
        ]);

        return response()->json([
            'message' => "Proveedor «{$supplier->name}» registrado.",
            'data' => ['id' => $supplier->id, 'name' => $supplier->name],
        ], 201);
    }

    public function show(Supplier $supplier)
    {
        return view('purchases.suppliers.show', [
            'supplier' => $supplier,
            'purchases' => $supplier->purchases()->latest('purchased_on')->latest('id')->limit(30)->get(),
            'expenses' => $supplier->expenses()->with('category')->latest('spent_on')->limit(15)->get(),
            'debt' => $supplier->debt(),
        ]);
    }

    public function edit(Supplier $supplier)
    {
        return view('purchases.suppliers.edit', compact('supplier'));
    }

    public function update(Request $request, Supplier $supplier)
    {
        $supplier->update($this->validated($request) + ['active' => $request->boolean('active')]);

        return redirect()->route('suppliers.show', $supplier)->with('success', 'Proveedor actualizado.');
    }

    /** Con compras o gastos no se borra: se da de baja. */
    public function destroy(Supplier $supplier)
    {
        if ($supplier->purchases()->exists() || $supplier->expenses()->exists()) {
            $supplier->update(['active' => false]);

            return redirect()->route('suppliers.index')->with('success', "«{$supplier->name}» se dio de baja: tiene compras o gastos.");
        }

        $supplier->delete();

        return redirect()->route('suppliers.index')->with('success', "Proveedor «{$supplier->name}» eliminado.");
    }

    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:30'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ], [
            'name.required' => 'Escribe el nombre del proveedor.',
        ]);

        $data['name'] = trim($data['name']);

        return $data;
    }
}
