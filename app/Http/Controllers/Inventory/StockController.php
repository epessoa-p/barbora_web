<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Support\StockManager;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StockController extends Controller
{
    public function __construct(protected StockManager $stock)
    {
    }

    /** Existencias por almacén, con el aviso de reposición. */
    public function index(Request $request)
    {
        $query = Stock::with(['product.category', 'warehouse'])
            ->whereHas('product', fn ($q) => $q->where('track_stock', true));

        if ($warehouseId = $request->integer('warehouse')) {
            $query->where('warehouse_id', $warehouseId);
        }

        if ($request->filled('q')) {
            $term = $request->string('q');
            $query->whereHas('product', fn ($q) => $q->search($term));
        }

        $stocks = $query->get()->sortBy(fn (Stock $s) => $s->product?->name)->values();

        if ($request->query('filter') === 'low') {
            $stocks = $stocks->filter->isLow()->values();
        }

        return view('inventory.stock.index', [
            'stocks' => $stocks,
            'warehouses' => Warehouse::where('active', true)->orderBy('name')->get(),
            'lowCount' => Stock::with('product')->get()->filter->isLow()->count(),
        ]);
    }

    /** Historial de entradas, salidas y ajustes. */
    public function movements(Request $request)
    {
        $query = StockMovement::with(['product', 'warehouse', 'creator'])->latest('id');

        if ($warehouseId = $request->integer('warehouse')) {
            $query->where('warehouse_id', $warehouseId);
        }

        if ($productId = $request->integer('product')) {
            $query->where('product_id', $productId);
        }

        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }

        if ($from = $request->query('from')) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $request->query('to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        return view('inventory.stock.movements', [
            'movements' => $query->paginate(25)->withQueryString(),
            'warehouses' => Warehouse::orderBy('name')->get(),
            'products' => Product::tracked()->orderBy('name')->get(),
        ]);
    }

    /** Formulario de entrada, salida o ajuste. */
    public function create(Request $request)
    {
        return view('inventory.stock.create', [
            'type' => in_array($request->query('type'), array_keys(StockMovement::TYPES), true)
                ? $request->query('type')
                : 'entrada',
            'products' => Product::tracked()->where('active', true)->with('stocks')->orderBy('name')->get(),
            'warehouses' => Warehouse::where('active', true)->orderBy('name')->get(),
            'selectedProduct' => $request->integer('product') ?: null,
        ]);
    }

    public function store(Request $request)
    {
        $companyId = $this->targetCompanyId();

        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(StockMovement::TYPES))],
            'product_id' => ['required', Rule::exists('products', 'id')->where('company_id', $companyId)],
            'warehouse_id' => ['required', Rule::exists('warehouses', 'id')->where('company_id', $companyId)],
            'quantity' => 'required|numeric|min:0',
            'reason' => ['nullable', Rule::in(array_keys(StockMovement::REASONS))],
            'unit_cost' => 'nullable|numeric|min:0',
            'reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ], [
            'quantity.required' => 'Indica la cantidad.',
        ]);

        $product = Product::findOrFail($data['product_id']);
        $warehouse = Warehouse::findOrFail($data['warehouse_id']);
        $quantity = (float) $data['quantity'];

        $attributes = [
            'reason' => $data['reason'] ?? null,
            'unit_cost' => $data['unit_cost'] ?? null,
            'reference' => $data['reference'] ?? null,
            'notes' => $data['notes'] ?? null,
        ];

        $movement = match ($data['type']) {
            'entrada' => $this->stock->receive($product, $warehouse, $quantity, $attributes),
            'salida' => $this->stock->issue($product, $warehouse, $quantity, $attributes),
            'ajuste' => $this->stock->adjustTo($product, $warehouse, $quantity, $attributes),
        };

        // «Entrada registrada» pero «Ajuste registrado»: la concordancia se
        // resuelve aquí en vez de encajar el género a la fuerza.
        $verb = $data['type'] === 'ajuste' ? 'registrado' : 'registrada';

        return redirect()->route('stock.index', ['warehouse' => $warehouse->id])
            ->with('success', "{$movement->typeLabel()} {$verb}: «{$product->name}» queda en "
                . $product->formatQuantity((float) $movement->quantity_after)
                . " en «{$warehouse->name}».");
    }
}
