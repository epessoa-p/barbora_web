<?php

namespace App\Support\Reports;

use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SaleItem;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Análisis: cómo va el negocio comparado con antes, sin elegir fechas.
 *
 * Tres series (servicios, ventas de productos y compras), cada una en monto y
 * en cantidad:
 *   · la semana y el mes en curso contra el MISMO TRAMO del anterior (si hoy
 *     es miércoles, de lunes a miércoles contra lunes a miércoles): comparar
 *     media semana contra una entera siempre saldría a la baja;
 *   · por día, la semana actual junto a la anterior;
 *   · las últimas 8 semanas y los últimos 6 meses.
 *
 * Y el top del mes: servicios, productos y clientes.
 */
class AnalysisReport
{
    public const SERIES = [
        'servicios' => 'Servicios',
        'ventas' => 'Ventas',
        'compras' => 'Compras',
    ];

    protected const DAYS = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];

    protected const MONTHS = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

    protected CarbonImmutable $today;

    public function for(?int $branchId = null, bool $withPurchases = true): array
    {
        $this->today = CarbonImmutable::today();

        // Desde lo más antiguo que se pinta: 6 meses o 8 semanas atrás.
        $from = min(
            $this->today->startOfMonth()->subMonthsNoOverflow(5),
            $this->today->startOfWeek(CarbonImmutable::MONDAY)->subWeeks(7),
        );

        $series = [
            'servicios' => $this->build($this->items('servicio', $from, $branchId)),
            'ventas' => $this->build($this->items('producto', $from, $branchId)),
        ];

        if ($withPurchases) {
            $series['compras'] = $this->build($this->purchases($from, $branchId));
        }

        return [
            'series' => $series,
            'top' => $this->top($branchId),
        ];
    }

    /* ── Datos por día ────────────────────────────────────────────────────── */

    /**
     * Líneas de venta cobradas de un tipo, por día: [Y-m-d => [amount, count]].
     * Cantidad = unidades vendidas (o veces que se hizo el servicio).
     */
    protected function items(string $type, CarbonImmutable $from, ?int $branchId): Collection
    {
        return SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sale_items.type', $type)
            ->where('sales.status', 'pagada')
            ->where('sales.sold_at', '>=', $from)
            ->when($branchId, fn ($q) => $q->where('sales.branch_id', $branchId))
            ->selectRaw('DATE(sales.sold_at) as day, SUM(sale_items.total) as amount, SUM(sale_items.quantity) as count')
            ->groupBy('day')
            ->get()
            ->mapWithKeys(fn ($r) => [(string) $r->day => [(float) $r->amount, (float) $r->count]]);
    }

    /** Compras no anuladas, por día. Cantidad = número de compras. */
    protected function purchases(CarbonImmutable $from, ?int $branchId): Collection
    {
        return Purchase::query()
            ->where('status', '!=', 'anulada')
            ->whereDate('purchased_on', '>=', $from->toDateString())
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->selectRaw('DATE(purchased_on) as day, SUM(total) as amount, COUNT(*) as count')
            ->groupBy('day')
            ->get()
            ->mapWithKeys(fn ($r) => [(string) $r->day => [(float) $r->amount, (float) $r->count]]);
    }

    /* ── Armado ───────────────────────────────────────────────────────────── */

    /** Las dos métricas de una serie. */
    protected function build(Collection $days): array
    {
        return [
            'amount' => $this->metric($days, 0),
            'count' => $this->metric($days, 1),
        ];
    }

    protected function metric(Collection $days, int $i): array
    {
        $sum = function (CarbonImmutable $from, CarbonImmutable $to) use ($days, $i): float {
            $total = 0.0;
            for ($d = $from; $d->lte($to); $d = $d->addDay()) {
                $total += $days[$d->toDateString()][$i] ?? 0;
            }

            return round($total, 2);
        };

        $today = $this->today;
        $weekStart = $today->startOfWeek(CarbonImmutable::MONDAY);
        $monthStart = $today->startOfMonth();

        // El mismo tramo del periodo anterior. Un mes más corto se recorta a su fin.
        $prevWeekStart = $weekStart->subWeek();
        $prevMonthStart = $monthStart->subMonthNoOverflow();
        $prevMonthTo = $prevMonthStart->addDays(min($today->day, $prevMonthStart->daysInMonth) - 1);

        $week = $sum($weekStart, $today);
        $weekPrev = $sum($prevWeekStart, $prevWeekStart->addDays($today->dayOfWeekIso - 1));
        $month = $sum($monthStart, $today);
        $monthPrev = $sum($prevMonthStart, $prevMonthTo);

        $daily = [];
        foreach (self::DAYS as $n => $label) {
            $daily[] = [
                'label' => $label,
                'current' => round($days[$weekStart->addDays($n)->toDateString()][$i] ?? 0, 2),
                'previous' => round($days[$prevWeekStart->addDays($n)->toDateString()][$i] ?? 0, 2),
            ];
        }

        $weeks = [];
        for ($w = 7; $w >= 0; $w--) {
            $start = $weekStart->subWeeks($w);
            $weeks[] = ['label' => $start->format('d/m'), 'value' => $sum($start, $start->addDays(6))];
        }

        $months = [];
        for ($m = 5; $m >= 0; $m--) {
            $start = $monthStart->subMonthsNoOverflow($m);
            $months[] = ['label' => self::MONTHS[$start->month - 1], 'value' => $sum($start, $start->endOfMonth()->startOfDay())];
        }

        return [
            'week' => $week,
            'week_previous' => $weekPrev,
            'week_change' => $this->change($week, $weekPrev),
            'month' => $month,
            'month_previous' => $monthPrev,
            'month_change' => $this->change($month, $monthPrev),
            'daily' => $daily,
            'weeks' => $weeks,
            'months' => $months,
        ];
    }

    /** Variación en %; null si antes no hubo nada con qué comparar. */
    protected function change(float $now, float $before): ?float
    {
        return $before > 0 ? round(($now - $before) / $before * 100, 1) : null;
    }

    /* ── Top del mes ──────────────────────────────────────────────────────── */

    protected function top(?int $branchId): array
    {
        $from = $this->today->startOfMonth();

        $items = fn (string $type) => SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->leftJoin($type === 'servicio' ? 'services as ref' : 'products as ref', 'ref.id', '=', $type === 'servicio' ? 'sale_items.service_id' : 'sale_items.product_id')
            ->where('sale_items.type', $type)
            ->where('sales.status', 'pagada')
            ->where('sales.sold_at', '>=', $from)
            ->when($branchId, fn ($q) => $q->where('sales.branch_id', $branchId))
            ->selectRaw('COALESCE(ref.name, MIN(sale_items.description)) as name, SUM(sale_items.total) as amount, SUM(sale_items.quantity) as count')
            // Sin ficha (venta rápida), cada descripción va por su lado.
            ->groupByRaw('ref.id, ref.name, CASE WHEN ref.id IS NULL THEN sale_items.description END')
            ->get();

        $clients = Sale::query()
            ->join('clients', 'clients.id', '=', 'sales.client_id')
            ->where('sales.status', 'pagada')
            ->where('sales.sold_at', '>=', $from)
            ->when($branchId, fn ($q) => $q->where('sales.branch_id', $branchId))
            ->selectRaw('clients.id, clients.full_name as name, SUM(sales.total) as amount, COUNT(*) as count')
            ->groupBy('clients.id', 'clients.full_name')
            ->get();

        $rows = fn (Collection $rows) => $rows
            ->map(fn ($r) => ['name' => (string) $r->name, 'amount' => round((float) $r->amount, 2), 'count' => (float) $r->count])
            ->sortByDesc('amount')
            ->take(50)
            ->values()
            ->all();

        return [
            'services' => $rows($items('servicio')),
            'products' => $rows($items('producto')),
            'clients' => $rows($clients),
        ];
    }
}
