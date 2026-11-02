<?php

namespace App\Http\Controllers\Purchases;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Finance\ExpenseController;
use App\Models\Branch;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Support\Money;
use App\Support\PurchaseRegistrar;
use Illuminate\Http\Request;

/**
 * Compras de mercadería a proveedores. Al registrarla entra al almacén; se
 * paga al contado (desde una cuenta o la caja abierta), en parte o a crédito.
 */
class PurchaseController extends Controller
{
    public function __construct(protected PurchaseRegistrar $registrar)
    {
    }

    public function index(Request $request)
    {
        $query = Purchase::with(['supplier', 'warehouse', 'branch'])->latest('purchased_on')->latest('id');

        if ($status = $request->query('status')) {
            $status === 'deuda'
                ? $query->whereIn('status', ['pendiente', 'parcial'])
                : $query->where('status', $status);
        }
        if ($supplier = $request->integer('supplier')) {
            $query->where('supplier_id', $supplier);
        }
        if ($from = $request->query('from')) {
            $query->whereDate('purchased_on', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $query->whereDate('purchased_on', '<=', $to);
        }

        $active = (clone $query)->reorder()->where('status', '!=', 'anulada');

        return view('purchases.index', [
            'purchases' => $query->paginate(25)->withQueryString(),
            'suppliers' => Supplier::orderBy('name')->get(),
            'summary' => [
                'total' => (float) (clone $active)->sum('total'),
                // Lo que se debe a proveedores en lo filtrado.
                'debt' => (float) (clone $active)->selectRaw('COALESCE(SUM(total - paid), 0) as d')->value('d'),
            ],
        ]);
    }

    public function create(Request $request)
    {
        $company = $request->attributes->get('tenant_company');
        $preferredBranch = Branch::preferredIdFor($request->user(), $company->id);
        $warehouses = Warehouse::where('active', true)->orderBy('name')->get();

        return view('purchases.create', [
            'suppliers' => Supplier::active()->orderBy('name')->get(),
            'warehouses' => $warehouses,
            // El almacén de la sucursal del usuario, si lo hay.
            'defaultWarehouse' => $warehouses->firstWhere('branch_id', $preferredBranch)?->id ?? $warehouses->first()?->id,
            // Para el selector de cada línea, con su último costo.
            'productOptions' => Product::where('active', true)->orderBy('name')->get()
                ->map(fn (Product $p) => [
                    'id' => $p->id, 'name' => $p->name,
                    'cost' => (float) $p->cost_price, 'stock' => (bool) $p->track_stock,
                ])->values(),
            ...ExpenseController::sourceOptions($company),
        ]);
    }

    public function store(Request $request)
    {
        $company = $request->attributes->get('tenant_company');
        $purchase = $this->registrar->register($request, $company);

        return redirect()->route('purchases.show', $purchase)->with('success',
            "Compra {$purchase->number} registrada: ".Money::format($purchase->total, $company)
            .($purchase->balance() > 0 ? ' · queda por pagar '.Money::format($purchase->balance(), $company).'.' : ', pagada.'));
    }

    public function show(Request $request, Purchase $purchase)
    {
        return view('purchases.show', [
            'purchase' => $purchase->load(['supplier', 'warehouse', 'branch', 'items', 'payments.account', 'payments.cashSession', 'creator']),
            ...ExpenseController::sourceOptions($request->attributes->get('tenant_company')),
        ]);
    }

    public function pay(Request $request, Purchase $purchase)
    {
        $company = $request->attributes->get('tenant_company');
        $payment = $this->registrar->pay($request, $purchase, $company);

        return redirect()->route('purchases.show', $purchase)->with('success',
            'Pago de '.Money::format($payment->amount, $company).' registrado.');
    }

    public function cancel(Request $request, Purchase $purchase)
    {
        $data = $request->validate(['cancel_reason' => ['nullable', 'string', 'max:255']]);

        $this->registrar->cancel($purchase, $data['cancel_reason'] ?? null, $request->user()->id);

        return redirect()->route('purchases.show', $purchase)
            ->with('success', "Compra {$purchase->number} anulada: la mercadería salió del almacén y los pagos se devolvieron.");
    }
}
