<?php

namespace App\Support\Reports;

use App\Models\Product;
use App\Models\SaleItem;
use App\Models\Stock;
use App\Support\ReportPeriod;
use Illuminate\Support\Collection;

/** Qué productos rotan, cuánto margen dejan y cuáles están por agotarse. */
class ProductsReport
{
    public function for(ReportPeriod $period): array
    {
        $rows = $this->ranking($period);

        return [
            'rows' => $rows,
            'totals' => [
                'quantity' => (float) $rows->sum('quantity'),
                'revenue' => (float) $rows->sum('revenue'),
                'cost' => (float) $rows->sum('cost'),
                'margin' => (float) $rows->sum('margin'),
            ],
            // Si ninguna línea hubo que estimarla, el margen es exacto y la
            // vista deja de avisar sola: no hay que acordarse de quitarlo.
            'estimated' => $rows->contains('estimated', true),
            'lowStock' => $this->lowStock(),
            'idle' => $this->idle($rows),
        ];
    }

    /**
     * Ranking de productos con su margen.
     *
     * El costo sale de `sale_items.unit_cost`, congelado el día de la venta.
     * Las líneas anteriores a que se empezara a guardar lo tienen en NULL: para
     * ésas se cae al costo actual del producto y se marcan como estimadas, para
     * no mezclar un dato con una suposición sin avisar.
     */
    public function ranking(ReportPeriod $period): Collection
    {
        return SaleItem::query()
            ->where('sale_items.type', 'producto')
            ->whereHas('sale', fn ($q) => $this->scopeSale($q, $period))
            ->with('product')
            ->selectRaw('product_id')
            ->selectRaw('MIN(description) as description')
            ->selectRaw('COUNT(*) as times')
            ->selectRaw('COALESCE(SUM(quantity), 0) as quantity')
            ->selectRaw('COALESCE(SUM(total), 0) as revenue')
            // Lo vendido con costo conocido, separado de lo que hay que estimar.
            ->selectRaw('COALESCE(SUM(CASE WHEN unit_cost IS NOT NULL THEN unit_cost * quantity ELSE 0 END), 0) as known_cost')
            ->selectRaw('COALESCE(SUM(CASE WHEN unit_cost IS NULL THEN quantity ELSE 0 END), 0) as unknown_quantity')
            ->groupBy('product_id')
            ->get()
            ->map(function (SaleItem $row) {
                $revenue = (float) $row->revenue;
                $quantity = (float) $row->quantity;

                $knownCost = (float) $row->known_cost;
                $unknownQuantity = (float) $row->unknown_quantity;

                // Solo para lo antiguo: costo actual del producto como apaño.
                $fallbackUnit = (float) ($row->product?->cost_price ?? 0);
                $estimatedCost = round($fallbackUnit * $unknownQuantity, 2);

                $cost = round($knownCost + $estimatedCost, 2);

                return [
                    'product_id' => $row->product_id,
                    'product' => $row->product?->name ?? $row->description,
                    'unit' => $row->product?->unit ?? 'unidad',
                    'times' => (int) $row->times,
                    'quantity' => $quantity,
                    'revenue' => $revenue,
                    'cost' => $cost,
                    'margin' => round($revenue - $cost, 2),
                    'margin_pct' => $revenue > 0 ? round(($revenue - $cost) / $revenue * 100, 1) : null,
                    // Verdadero solo si alguna línea tuvo que estimarse.
                    'estimated' => $unknownQuantity > 0,
                    'has_cost' => $cost > 0,
                ];
            })
            ->sortByDesc('revenue')
            ->values();
    }

    /** Productos por debajo de su umbral de aviso. */
    public function lowStock(): Collection
    {
        return Stock::with(['product', 'warehouse'])
            ->whereHas('product', fn ($q) => $q->where('track_stock', true)->where('active', true))
            ->get()
            ->filter(fn (Stock $stock) => $stock->product
                && $stock->product->min_stock > 0
                && $stock->quantity <= $stock->product->min_stock)
            ->map(fn (Stock $stock) => [
                'product' => $stock->product->name,
                'warehouse' => $stock->warehouse?->name ?? '—',
                'quantity' => (float) $stock->quantity,
                'min_stock' => (float) $stock->product->min_stock,
                'unit' => $stock->product->unit,
            ])
            ->sortBy('quantity')
            ->values();
    }

    /**
     * Productos a la venta que no movieron una sola unidad en el periodo:
     * es dinero parado en la estantería.
     */
    public function idle(Collection $sold): Collection
    {
        $soldIds = $sold->pluck('product_id')->filter()->all();

        return Product::sellable()->where('active', true)
            ->orderBy('name')
            ->get()
            ->reject(fn (Product $product) => in_array($product->id, $soldIds, true))
            ->map(fn (Product $product) => [
                'product' => $product->name,
                'sale_price' => (float) $product->sale_price,
                'unit' => $product->unit,
            ])
            ->values();
    }

    protected function scopeSale($query, ReportPeriod $period): void
    {
        $query->where('sales.status', 'pagada')
              ->whereBetween('sales.sold_at', $period->range());

        if ($period->branchId) {
            $query->where('sales.branch_id', $period->branchId);
        }
    }
}
