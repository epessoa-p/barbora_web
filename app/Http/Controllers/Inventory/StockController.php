<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Support\StockManager;
use App\Support\StockMovementForm;
use Illuminate\Http\Request;

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

    /** Las reglas son las mismas que en la API: ver StockMovementForm. */
    public function store(Request $request, StockMovementForm $form)
    {
        $movement = $form->register($request, $this->targetCompanyId());

        return redirect()->route('stock.index', ['warehouse' => $movement->warehouse_id])
            ->with('success', $form->message($movement));
    }
}
