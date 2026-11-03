<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Branch;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchasePayment;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Support\PurchaseRegistrar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Compras y proveedores desde el móvil. Mismas reglas que la web
 * (PurchaseRegistrar, PaymentSource) y mismos permisos.
 */
class PurchaseController extends ApiController
{
    public function __construct(protected PurchaseRegistrar $registrar)
    {
    }

    /* ── Compras ─────────────────────────────────────────────────────────── */

    public function index(Request $request): JsonResponse
    {
        $query = Purchase::with(['supplier', 'warehouse'])->latest('purchased_on')->latest('id');

        if ($request->query('status') === 'deuda') {
            $query->whereIn('status', ['pendiente', 'parcial']);
        } elseif ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($supplier = $request->integer('supplier')) {
            $query->where('supplier_id', $supplier);
        }

        $active = (clone $query)->reorder()->where('status', '!=', 'anulada');
        $page = $query->paginate(30);

        return $this->json([
            'data' => collect($page->items())->map($this->purchasePayload(...))->all(),
            'summary' => [
                'total' => round((float) (clone $active)->sum('total'), 2),
                'debt' => round((float) (clone $active)->selectRaw('COALESCE(SUM(total - paid), 0) as d')->value('d'), 2),
            ],
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage()],
        ]);
    }

    /** Lo que necesita el alta: proveedores, almacenes, productos y de dónde sale el dinero. */
    public function options(Request $request): JsonResponse
    {
        $company = $request->attributes->get('tenant_company');
        $preferred = Branch::preferredIdFor($request->user(), $company->id);
        // Los almacenes de sus sucursales (y los centrales, sin sucursal).
        $warehouses = Branch::limitToWorkable(Warehouse::where('active', true), 'branch_id', $request->user(), $company->id)
            ->orderBy('name')->get();

        return $this->json([
            'suppliers' => Supplier::active()->orderBy('name')->get()->map(fn (Supplier $s) => ['id' => $s->id, 'name' => $s->name])->all(),
            'warehouses' => $warehouses->map(fn (Warehouse $w) => ['id' => $w->id, 'name' => $w->name])->all(),
            'default_warehouse_id' => $warehouses->firstWhere('branch_id', $preferred)?->id ?? $warehouses->first()?->id,
            'products' => Product::where('active', true)->orderBy('name')->get()->map(fn (Product $p) => [
                'id' => $p->id, 'name' => $p->name, 'cost' => (float) $p->cost_price, 'track_stock' => (bool) $p->track_stock,
            ])->all(),
            ...FinanceController::sourcePayload($company),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $purchase = $this->registrar->register($request, $request->attributes->get('tenant_company'));

        return $this->json([
            'message' => "Compra {$purchase->number} registrada.",
            'data' => $this->detailPayload($purchase),
        ], 201);
    }

    public function show(Purchase $purchase): JsonResponse
    {
        return $this->json(['data' => $this->detailPayload($purchase)]);
    }

    public function pay(Request $request, Purchase $purchase): JsonResponse
    {
        $payment = $this->registrar->pay($request, $purchase, $request->attributes->get('tenant_company'));

        return $this->json([
            'message' => 'Pago registrado.',
            'data' => $this->detailPayload($purchase->refresh()),
            'payment_id' => $payment->id,
        ], 201);
    }

    public function cancel(Request $request, Purchase $purchase): JsonResponse
    {
        $data = $request->validate(['cancel_reason' => ['nullable', 'string', 'max:255']]);

        $this->registrar->cancel($purchase, $data['cancel_reason'] ?? null, $request->user()->id);

        return $this->json([
            'message' => "Compra {$purchase->number} anulada: la mercadería salió del almacén y los pagos se devolvieron.",
            'data' => $this->detailPayload($purchase->refresh()),
        ]);
    }

    /* ── Proveedores ─────────────────────────────────────────────────────── */

    public function suppliers(Request $request): JsonResponse
    {
        $suppliers = Supplier::withCount('purchases')->orderByDesc('active')->orderBy('name')->get();
        $debts = Purchase::whereIn('supplier_id', $suppliers->pluck('id'))
            ->where('status', '!=', 'anulada')
            ->groupBy('supplier_id')
            ->selectRaw('supplier_id, SUM(total - paid) as debt')
            ->pluck('debt', 'supplier_id');

        return $this->json([
            'data' => $suppliers->map(fn (Supplier $s) => $this->supplierPayload($s, (float) ($debts[$s->id] ?? 0)))->all(),
        ]);
    }

    public function storeSupplier(Request $request): JsonResponse
    {
        $supplier = Supplier::create($this->validateSupplier($request) + [
            'company_id' => $request->attributes->get('tenant_company')->id,
            'active' => true,
        ]);

        return $this->json(['message' => "Proveedor «{$supplier->name}» registrado.", 'data' => $this->supplierPayload($supplier, 0)], 201);
    }

    public function updateSupplier(Request $request, Supplier $supplier): JsonResponse
    {
        $supplier->update($this->validateSupplier($request) + ['active' => $request->boolean('active', (bool) $supplier->active)]);

        return $this->json(['message' => 'Proveedor actualizado.', 'data' => $this->supplierPayload($supplier, $supplier->debt())]);
    }

    /* ── Internos ────────────────────────────────────────────────────────── */

    protected function validateSupplier(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:30'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'active' => ['sometimes', 'boolean'],
        ], [
            'name.required' => 'Escribe el nombre del proveedor.',
        ]);

        unset($data['active']);
        $data['name'] = trim($data['name']);

        return $data;
    }

    protected function supplierPayload(Supplier $supplier, float $debt): array
    {
        return [
            'id' => $supplier->id,
            'name' => $supplier->name,
            'tax_id' => $supplier->tax_id,
            'contact_name' => $supplier->contact_name,
            'phone' => $supplier->phone,
            'email' => $supplier->email,
            'address' => $supplier->address,
            'notes' => $supplier->notes,
            'active' => (bool) $supplier->active,
            'purchases_count' => (int) ($supplier->purchases_count ?? 0),
            'debt' => round($debt, 2),
        ];
    }

    protected function purchasePayload(Purchase $purchase): array
    {
        return [
            'id' => $purchase->id,
            'number' => $purchase->number,
            'supplier' => $purchase->supplier?->name,
            'warehouse' => $purchase->warehouse?->name,
            'invoice_number' => $purchase->invoice_number,
            'purchased_on' => $purchase->purchased_on->toDateString(),
            'due_on' => $purchase->due_on?->toDateString(),
            'total' => (float) $purchase->total,
            'paid' => (float) $purchase->paid,
            'balance' => $purchase->balance(),
            'status' => $purchase->status,
            'status_label' => $purchase->statusLabel(),
        ];
    }

    protected function detailPayload(Purchase $purchase): array
    {
        $purchase->load(['supplier', 'warehouse', 'items', 'payments.account', 'payments.cashSession']);

        return $this->purchasePayload($purchase) + [
            'subtotal' => (float) $purchase->subtotal,
            'discount' => (float) $purchase->discount,
            'notes' => $purchase->notes,
            'cancel_reason' => $purchase->cancel_reason,
            'items' => $purchase->items->map(fn ($i) => [
                'product' => $i->product_name,
                'quantity' => (float) $i->quantity,
                'unit_cost' => (float) $i->unit_cost,
                'total' => (float) $i->total,
            ])->all(),
            'payments' => $purchase->payments->map(fn (PurchasePayment $p) => [
                'id' => $p->id,
                'amount' => (float) $p->amount,
                'paid_on' => $p->paid_on->toDateString(),
                'source' => $p->source,
                'source_label' => $p->sourceLabel(),
                'reference' => $p->reference,
                'cancelled' => $p->cancelled_at !== null,
            ])->all(),
        ];
    }
}
