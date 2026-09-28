<?php

namespace App\Support\Reports;

use App\Models\PaymentMethod;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Support\ReportPeriod;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Qué se vendió en el periodo.
 *
 * Todas las consultas van sobre el modelo Sale, nunca sobre DB::table(): así el
 * CompanyScope sigue aplicando y un reporte no puede sumar las ventas de otra
 * barbería. Vale para todos los reportes de este directorio.
 */
class SalesReport
{
    public function for(ReportPeriod $period): array
    {
        $totals = $this->totals($period);
        $previous = $this->totals($period->previous());

        return [
            'totals' => $totals,
            'previous' => $previous,
            'change' => $this->change($totals, $previous),
            'daily' => $this->daily($period),
            'byBranch' => $this->byBranch($period),
            'byMethod' => $this->byPaymentMethod($period),
        ];
    }

    /**
     * Cómo pagó la gente. Vive en sale_payments, no en sales, porque una venta
     * admite pago mixto (parte en efectivo, parte con tarjeta).
     */
    public function byPaymentMethod(ReportPeriod $period): Collection
    {
        return SalePayment::query()
            ->whereHas('sale', function ($q) use ($period) {
                $q->where('sales.status', 'pagada')
                  ->whereBetween('sales.sold_at', $period->range());

                if ($period->branchId) {
                    $q->where('sales.branch_id', $period->branchId);
                }
            })
            ->selectRaw('payment_method')
            ->selectRaw('COUNT(*) as payments')
            ->selectRaw('COALESCE(SUM(amount), 0) as total')
            ->groupBy('payment_method')
            ->get()
            ->pipe(function ($rows) {
                // Los nombres se leen una vez, no por fila. Se incluyen los
                // dados de baja: un método retirado tiene que seguir teniendo
                // nombre en los reportes de cuando se usaba.
                $labels = PaymentMethod::withTrashed()->pluck('name', 'slug');

                return $rows->map(fn (SalePayment $row) => [
                    'method' => $labels[$row->payment_method]
                        ?? Str::headline($row->payment_method),
                    'payments' => (int) $row->payments,
                    'total' => (float) $row->total,
                ]);
            })
            ->sortByDesc('total')
            ->values();
    }

    /** @return array<string, float|int> */
    public function totals(ReportPeriod $period): array
    {
        $paid = $this->base($period)->paid()
            ->selectRaw('COUNT(*) as sales')
            ->selectRaw('COALESCE(SUM(subtotal), 0) as subtotal')
            ->selectRaw('COALESCE(SUM(discount), 0) as discount')
            ->selectRaw('COALESCE(SUM(tip), 0) as tip')
            ->selectRaw('COALESCE(SUM(total), 0) as total')
            ->first();

        $cancelled = $this->base($period)->where('status', 'anulada')
            ->selectRaw('COUNT(*) as sales')
            ->selectRaw('COALESCE(SUM(total), 0) as total')
            ->first();

        $sales = (int) $paid->sales;

        return [
            'sales' => $sales,
            'subtotal' => (float) $paid->subtotal,
            'discount' => (float) $paid->discount,
            'tip' => (float) $paid->tip,
            'total' => (float) $paid->total,
            // El ticket medio excluye la propina: mide lo que compra el cliente.
            'ticket' => $sales > 0 ? round(((float) $paid->total - (float) $paid->tip) / $sales, 2) : 0.0,
            'cancelled' => (int) $cancelled->sales,
            'cancelled_total' => (float) $cancelled->total,
        ];
    }

    /** Serie por día, con los días sin ventas rellenos a cero para el gráfico. */
    public function daily(ReportPeriod $period): Collection
    {
        // DATE() existe igual en MySQL y en SQLite, así que la misma consulta
        // vale en producción y en los tests.
        $rows = $this->base($period)->paid()
            ->selectRaw('DATE(sold_at) as day')
            ->selectRaw('COUNT(*) as sales')
            ->selectRaw('COALESCE(SUM(total), 0) as total')
            ->groupBy(DB::raw('DATE(sold_at)'))
            ->get()
            ->keyBy('day');

        $series = collect();
        $cursor = $period->from->startOfDay();

        while ($cursor->lessThanOrEqualTo($period->to)) {
            $key = $cursor->toDateString();

            $series->push([
                'date' => $key,
                'label' => $cursor->translatedFormat('d/m'),
                'total' => (float) ($rows[$key]->total ?? 0),
                'sales' => (int) ($rows[$key]->sales ?? 0),
            ]);

            $cursor = $cursor->addDay();
        }

        return $series;
    }

    public function byBranch(ReportPeriod $period): Collection
    {
        return $this->base($period)->paid()
            ->with('branch')
            ->selectRaw('branch_id')
            ->selectRaw('COUNT(*) as sales')
            ->selectRaw('COALESCE(SUM(total), 0) as total')
            ->groupBy('branch_id')
            ->get()
            ->map(fn (Sale $row) => [
                'branch' => $row->branch?->name ?? 'Sin sucursal',
                'sales' => (int) $row->sales,
                'total' => (float) $row->total,
            ])
            ->sortByDesc('total')
            ->values();
    }

    /** Variación porcentual contra el periodo anterior del mismo tamaño. */
    protected function change(array $totals, array $previous): array
    {
        $pct = function (float $now, float $before): ?float {
            // Sin base de comparación, un porcentaje no significa nada.
            if ($before <= 0.0) {
                return null;
            }

            return round((($now - $before) / $before) * 100, 1);
        };

        return [
            'total' => $pct((float) $totals['total'], (float) $previous['total']),
            'sales' => $pct((float) $totals['sales'], (float) $previous['sales']),
            'ticket' => $pct((float) $totals['ticket'], (float) $previous['ticket']),
        ];
    }

    protected function base(ReportPeriod $period)
    {
        $query = Sale::whereBetween('sold_at', $period->range());

        if ($period->branchId) {
            $query->where('branch_id', $period->branchId);
        }

        return $query;
    }
}
