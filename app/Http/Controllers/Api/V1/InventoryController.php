<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Company;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Support\ProductForm;
use App\Support\ShopTime;
use App\Support\StockMovementForm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Inventario desde el móvil: productos, existencias y movimientos.
 *
 * Mismas reglas que la web (ProductForm, StockMovementForm) y el stock se
 * mueve solo por StockManager. `GET /products` sigue existiendo aparte: es el
 * catálogo para cobrar, solo lo vendible.
 */
class InventoryController extends ApiController
{
    /**
     * Todos los productos (no solo los vendibles), con su existencia total.
     * `low=1` deja solo los que están en o por debajo del mínimo.
     */
    public function index(Request $request): JsonResponse
    {
        $products = Product::with(['category', 'stocks'])
            ->search($request->query('q'))
            ->when($request->integer('category_id'), fn ($q, $id) => $q->where('product_category_id', $id))
            ->when(! $request->boolean('all'), fn ($q) => $q->where('active', true))
            ->orderBy('name')
            ->get();

        if ($request->boolean('low')) {
            $products = $products->filter->isLowStock()->values();
        }

        return $this->json([
            'data' => $products->map(fn (Product $p) => $this->summary($p))->all(),
            'low_count' => Product::tracked()->where('active', true)->with('stocks')->get()
                ->filter->isLowStock()->count(),
        ]);
    }

    /** Lo que necesitan los formularios: categorías, almacenes, unidades, motivos. */
    public function options(Request $request): JsonResponse
    {
        $limit = $this->planLimitStatus($this->companyId($request), 'products');

        return $this->json([
            'categories' => ProductCategory::where('active', true)->orderBy('sort_order')->orderBy('name')->get()
                ->map(fn (ProductCategory $c) => ['id' => $c->id, 'name' => $c->name])->all(),
            'warehouses' => Warehouse::where('active', true)->orderBy('name')->get()
                ->map(fn (Warehouse $w) => ['id' => $w->id, 'name' => $w->name])->all(),
            'units' => $this->pairs(Product::UNITS),
            'types' => $this->pairs(StockMovement::TYPES),
            'reasons' => $this->pairs(StockMovement::REASONS),
            'limit' => $limit,
        ]);
    }

    /** La ficha: datos, existencias por almacén y últimos movimientos. */
    public function show(Request $request, Product $product): JsonResponse
    {
        $product->load(['category', 'stocks.warehouse']);
        $company = $this->company($request);

        $movements = $product->movements()->with(['warehouse', 'creator'])->limit(20)->get();

        return $this->json([
            'data' => $this->summary($product) + [
                'description' => $product->description,
                'cost_price' => (float) $product->cost_price,
                'margin_percent' => $product->marginPercent(),
                'is_sellable' => (bool) $product->is_sellable,
                'stocks' => $product->stocks->map(fn (Stock $s) => [
                    'warehouse_id' => $s->warehouse_id,
                    'warehouse' => $s->warehouse?->name,
                    'quantity' => (float) $s->quantity,
                    'quantity_label' => $product->formatQuantity((float) $s->quantity),
                ])->values()->all(),
                'movements' => $movements->map(fn (StockMovement $m) => [
                    'id' => $m->id,
                    'type' => $m->type,
                    'type_label' => $m->typeLabel(),
                    'reason_label' => $m->reasonLabel(),
                    'quantity' => (float) $m->quantity,
                    'quantity_after' => (float) $m->quantity_after,
                    'warehouse' => $m->warehouse?->name,
                    'reference' => $m->reference,
                    'notes' => $m->notes,
                    'created_by' => $m->creator?->name,
                    'created_at' => ShopTime::iso($m->created_at, $company),
                ])->all(),
            ],
        ]);
    }

    public function store(Request $request, ProductForm $form): JsonResponse
    {
        $companyId = $this->companyId($request);

        if ($this->planLimitReached($companyId, 'products')) {
            return $this->json([
                'error' => 'plan_limit',
                'message' => 'Alcanzaste el límite de productos de tu plan. Contacta a tu proveedor para ampliarlo.',
            ], 422);
        }

        $product = Product::create($form->validate($request, $companyId) + ['company_id' => $companyId]);

        return $this->json([
            'message' => "Producto «{$product->name}» creado.",
            'data' => $this->summary($product->load(['category', 'stocks'])),
        ], 201);
    }

    public function update(Request $request, Product $product, ProductForm $form): JsonResponse
    {
        $product->update($form->validate($request, $product->company_id, $product));

        return $this->json([
            'message' => 'Producto actualizado.',
            'data' => $this->summary($product->refresh()->load(['category', 'stocks'])),
        ]);
    }

    /** Entrada, salida o ajuste (recuento): mismas reglas que la web. */
    public function storeMovement(Request $request, StockMovementForm $form): JsonResponse
    {
        $movement = $form->register($request, $this->companyId($request));
        $movement->load(['product.stocks', 'product.category', 'warehouse']);

        return $this->json([
            'message' => $form->message($movement),
            'data' => $this->summary($movement->product),
        ], 201);
    }

    /* ---------------------------------------------------------------------
     | Internos
     |--------------------------------------------------------------------- */

    protected function summary(Product $p): array
    {
        $total = $p->track_stock ? $p->totalStock() : null;

        return [
            'id' => $p->id,
            'name' => $p->name,
            'sku' => $p->sku,
            'category_id' => $p->product_category_id,
            'category' => $p->category?->name,
            'unit' => $p->unit,
            'unit_label' => $p->unitLabel(),
            'sale_price' => (float) $p->sale_price,
            'cost_price' => (float) $p->cost_price,
            'min_stock' => (float) $p->min_stock,
            'track_stock' => (bool) $p->track_stock,
            'is_sellable' => (bool) $p->is_sellable,
            'active' => (bool) $p->active,
            // Null cuando no se lleva control: no es lo mismo que cero.
            'stock' => $total,
            'stock_label' => $total === null ? null : $p->formatQuantity($total),
            'low_stock' => $p->isLowStock(),
        ];
    }

    /** @param  array<string, string>  $map */
    protected function pairs(array $map): array
    {
        return collect($map)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values()->all();
    }

    protected function company(Request $request): ?Company
    {
        return $request->attributes->get('tenant_company');
    }

    protected function companyId(Request $request): ?int
    {
        return $this->company($request)?->id;
    }
}
