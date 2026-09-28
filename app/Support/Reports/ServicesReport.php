<?php

namespace App\Support\Reports;

use App\Models\Appointment;
use App\Models\SaleItem;
use App\Support\ReportPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Qué servicios se piden y cuánto dejan. */
class ServicesReport
{
    public function for(ReportPeriod $period): array
    {
        $rows = $this->ranking($period);

        return [
            'rows' => $rows,
            'totals' => [
                'quantity' => (float) $rows->sum('quantity'),
                'revenue' => (float) $rows->sum('revenue'),
                'services' => $rows->count(),
            ],
            'appointments' => $this->appointments($period),
        ];
    }

    /** Ranking por ingreso, que es lo que decide qué conviene promocionar. */
    public function ranking(ReportPeriod $period): Collection
    {
        return SaleItem::query()
            ->where('sale_items.type', 'servicio')
            ->whereHas('sale', fn ($q) => $this->scopeSale($q, $period))
            ->with('service')
            ->selectRaw('service_id')
            ->selectRaw('MIN(description) as description')
            ->selectRaw('COUNT(*) as times')
            ->selectRaw('COALESCE(SUM(quantity), 0) as quantity')
            ->selectRaw('COALESCE(SUM(total), 0) as revenue')
            ->groupBy('service_id')
            ->get()
            ->map(fn (SaleItem $row) => [
                'service' => $row->service?->name ?? $row->description,
                'active' => $row->service?->active ?? false,
                'times' => (int) $row->times,
                'quantity' => (float) $row->quantity,
                'revenue' => (float) $row->revenue,
                'average' => $row->times > 0 ? round((float) $row->revenue / (int) $row->times, 2) : 0.0,
            ])
            ->sortByDesc('revenue')
            ->values();
    }

    /**
     * Citas del periodo por estado. Es el otro lado de la foto: un servicio
     * puede pedirse mucho y cobrarse poco si se acumulan los «no_show».
     */
    public function appointments(ReportPeriod $period): array
    {
        $query = Appointment::whereBetween('starts_at', $period->range());

        if ($period->branchId) {
            $query->where('branch_id', $period->branchId);
        }

        $byStatus = $query->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $total = (int) $byStatus->sum();
        $attended = (int) ($byStatus['atendida'] ?? 0);
        $noShow = (int) ($byStatus['no_show'] ?? 0);

        return [
            'total' => $total,
            'attended' => $attended,
            'no_show' => $noShow,
            'cancelled' => (int) ($byStatus['cancelada'] ?? 0),
            'pending' => (int) ($byStatus['reservada'] ?? 0) + (int) ($byStatus['confirmada'] ?? 0),
            'attendance_rate' => $total > 0 ? round($attended / $total * 100, 1) : null,
            'no_show_rate' => $total > 0 ? round($noShow / $total * 100, 1) : null,
        ];
    }

    /** Filtra las líneas por la venta a la que pertenecen. */
    protected function scopeSale($query, ReportPeriod $period): void
    {
        $query->where('sales.status', 'pagada')
              ->whereBetween('sales.sold_at', $period->range());

        if ($period->branchId) {
            $query->where('sales.branch_id', $period->branchId);
        }
    }
}
