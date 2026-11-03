<?php

namespace App\Support\Reports;

use App\Models\CommissionEntry;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Support\ReportPeriod;
use Illuminate\Support\Collection;

/**
 * Cuánto se gana con lo que se vende: por productos (ventas) y por servicios.
 *
 *   Productos  ingreso − costo de compra (el congelado en la venta) − comisiones
 *   Servicios  ingreso − comisiones del barbero (lo que «cuesta» el servicio)
 *
 * El ingreso de cada línea lleva aplicado el descuento de su venta (repartido
 * en proporción), y las propinas no entran: son del barbero.
 *
 * Ventas rápidas: productos vendidos sin ficha de inventario (se tienen pero
 * no se registraron). No tienen costo conocido, así que por defecto quedan
 * FUERA; con $includeQuick entran con costo cero y se avisa.
 *
 * $scope: «ambos», «ventas» (solo productos) o «servicios».
 */
class ProfitReport
{
    public const SCOPES = ['ambos', 'ventas', 'servicios'];

    public function for(ReportPeriod $period, string $scope = 'ambos', bool $includeQuick = false): array
    {
        $scope = in_array($scope, self::SCOPES, true) ? $scope : 'ambos';

        $sales = Sale::paid()
            ->with(['items', 'client'])
            ->whereBetween('sold_at', $period->range())
            ->when($period->branchId, fn ($q) => $q->where('branch_id', $period->branchId))
            ->orderByDesc('sold_at')
            ->get();

        // Comisión de cada línea: lo que se le paga al barbero por ella.
        $commissions = CommissionEntry::whereIn('sale_id', $sales->pluck('id'))
            ->get()->groupBy('sale_item_id')->map(fn ($rows) => (float) $rows->sum('amount'));

        // Para las líneas viejas sin costo guardado: el costo actual del producto.
        $currentCost = Product::withTrashed()
            ->whereIn('id', $sales->flatMap->items->pluck('product_id')->filter()->unique())
            ->pluck('cost_price', 'id');

        $lines = $sales->flatMap(fn (Sale $sale) => $this->lines($sale, $commissions, $currentCost));

        $quick = $lines->where('quick', true);
        $counted = $lines
            ->when(! $includeQuick, fn ($c) => $c->where('quick', false))
            ->filter(fn ($l) => $scope === 'ambos'
                || ($scope === 'ventas' && $l['kind'] === 'producto')
                || ($scope === 'servicios' && $l['kind'] === 'servicio'));

        return [
            'scope' => $scope,
            'include_quick' => $includeQuick,
            'summary' => $this->totals($counted),
            'products' => $this->totals($counted->where('kind', 'producto')) + [
                'sales' => $counted->where('kind', 'producto')->pluck('sale_id')->unique()->count(),
            ],
            'services' => $this->totals($counted->where('kind', 'servicio')) + [
                'sales' => $counted->where('kind', 'servicio')->pluck('sale_id')->unique()->count(),
            ],
            // Lo vendido sin ficha en el periodo, entre o no en las cifras.
            'quick' => [
                'revenue' => round($quick->sum('revenue'), 2),
                'count' => $quick->count(),
            ],
            'daily' => $this->daily($counted),
            'top_products' => $this->products($counted)->sortByDesc('profit')->take(10)->values()->all(),
            'low_margin_products' => $this->products($counted)->where('revenue', '>', 0)->sortBy('margin_pct')->take(10)->values()->all(),
            'sales' => $this->sales($sales, $counted),
            // Verdadero si alguna línea no tenía el costo guardado y se estimó.
            'estimated' => $counted->contains('estimated', true),
        ];
    }

    /** Las líneas de una venta, con su ingreso (con descuento), costo y ganancia. */
    protected function lines(Sale $sale, Collection $commissions, Collection $currentCost): Collection
    {
        $subtotal = (float) $sale->items->sum('total');
        // El descuento de la venta se reparte entre sus líneas en proporción.
        $factor = $subtotal > 0 ? max(0, ($subtotal - (float) $sale->discount) / $subtotal) : 1;

        return $sale->items->map(function (SaleItem $item) use ($sale, $factor, $commissions, $currentCost) {
            $revenue = round((float) $item->total * $factor, 2);
            $quick = $item->type === 'producto' && ! $item->product_id;
            $estimated = false;

            if ($item->type === 'producto') {
                $unitCost = $item->unit_cost;

                if ($unitCost === null && ! $quick) {
                    $unitCost = (float) ($currentCost[$item->product_id] ?? 0);
                    $estimated = true;
                }

                $cost = round((float) $unitCost * (float) $item->quantity, 2);
            } else {
                $cost = 0.0;
            }

            $cost += (float) ($commissions[$item->id] ?? 0);

            return [
                'sale_id' => $sale->id,
                'date' => $sale->sold_at->toDateString(),
                'kind' => $item->type,
                'quick' => $quick,
                'product_id' => $item->product_id,
                'name' => $item->description,
                'quantity' => (float) $item->quantity,
                'revenue' => $revenue,
                'cost' => round($cost, 2),
                'profit' => round($revenue - $cost, 2),
                'estimated' => $estimated,
            ];
        });
    }

    protected function totals(Collection $lines): array
    {
        $revenue = round((float) $lines->sum('revenue'), 2);
        $cost = round((float) $lines->sum('cost'), 2);
        $profit = round($revenue - $cost, 2);

        return [
            'revenue' => $revenue,
            'cost' => $cost,
            'profit' => $profit,
            'margin_pct' => $revenue > 0 ? round($profit / $revenue * 100, 1) : null,
        ];
    }

    protected function daily(Collection $lines): array
    {
        return $lines->groupBy('date')
            ->sortKeysDesc()
            ->map(fn ($rows, $date) => ['date' => $date] + $this->totals($rows))
            ->values()->all();
    }

    /** Por producto (las ventas rápidas, por su nombre). */
    protected function products(Collection $lines): Collection
    {
        return $lines->where('kind', 'producto')
            ->groupBy(fn ($l) => $l['product_id'] ? 'p'.$l['product_id'] : 'q'.$l['name'])
            ->map(fn ($rows) => [
                'name' => $rows->first()['name'],
                'quick' => $rows->first()['quick'],
                'quantity' => (float) $rows->sum('quantity'),
            ] + $this->totals($rows))
            ->values();
    }

    /** Cada venta con lo que entra en el filtro (sin las que quedan en cero). */
    protected function sales(Collection $sales, Collection $lines): array
    {
        $bySale = $lines->groupBy('sale_id');

        return $sales->filter(fn (Sale $s) => $bySale->has($s->id))
            ->take(100)
            ->map(fn (Sale $s) => [
                'id' => $s->id,
                'number' => $s->number,
                'sold_at' => $s->sold_at->toIso8601String(),
                'client' => $s->client?->full_name,
            ] + $this->totals($bySale[$s->id]))
            ->values()->all();
    }
}
