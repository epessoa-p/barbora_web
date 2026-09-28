<?php

namespace App\Support\Reports;

use App\Models\Appointment;
use App\Models\CommissionEntry;
use App\Models\Personal;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Support\ReportPeriod;
use Illuminate\Support\Collection;

/** Rendimiento de cada barbero: qué produjo y qué se le debe. */
class StaffReport
{
    public function for(ReportPeriod $period): array
    {
        $rows = $this->ranking($period);

        return [
            'rows' => $rows,
            'totals' => [
                'revenue' => (float) $rows->sum('revenue'),
                'commissions' => (float) $rows->sum('commissions'),
                'tips' => (float) $rows->sum('tips'),
                'attended' => (int) $rows->sum('attended'),
            ],
        ];
    }

    public function ranking(ReportPeriod $period): Collection
    {
        $revenue = $this->revenueByPerson($period);
        $commissions = $this->commissionsByPerson($period);
        $tips = $this->tipsByPerson($period);
        $appointments = $this->appointmentsByPerson($period);

        // Se parte del personal, no de las ventas, para no perder a nadie. Y sin
        // filtrar por «bookable» ni por «active»: quien vendió producto en
        // mostrador, o quien ya se fue de la barbería, tiene que seguir
        // apareciendo en el periodo en que produjo, o las sumas no cuadrarían
        // con el reporte de ventas.
        return Personal::orderBy('full_name')->get()
            ->map(function (Personal $person) use ($revenue, $commissions, $tips, $appointments) {
                $own = $revenue->get($person->id);
                $cites = $appointments->get($person->id, collect());

                $attended = (int) ($cites['atendida'] ?? 0);
                $noShow = (int) ($cites['no_show'] ?? 0);
                $totalCites = (int) $cites->sum();

                return [
                    'personal' => $person,
                    'items' => (int) ($own['items'] ?? 0),
                    'revenue' => (float) ($own['revenue'] ?? 0),
                    'services' => (int) ($own['services'] ?? 0),
                    'products' => (int) ($own['products'] ?? 0),
                    'commissions' => (float) ($commissions[$person->id] ?? 0),
                    'tips' => (float) ($tips[$person->id] ?? 0),
                    'appointments' => $totalCites,
                    'attended' => $attended,
                    'no_show' => $noShow,
                    'no_show_rate' => $totalCites > 0 ? round($noShow / $totalCites * 100, 1) : null,
                    'average' => ($own['items'] ?? 0) > 0
                        ? round((float) $own['revenue'] / (int) $own['items'], 2)
                        : 0.0,
                ];
            })
            ->filter(fn (array $row) => $row['revenue'] > 0 || $row['appointments'] > 0 || $row['tips'] > 0)
            ->sortByDesc('revenue')
            ->values();
    }

    /** Lo facturado por cada barbero, atribuido línea a línea. */
    protected function revenueByPerson(ReportPeriod $period): Collection
    {
        return SaleItem::query()
            ->whereNotNull('personal_id')
            ->whereHas('sale', fn ($q) => $this->scopeSale($q, $period))
            ->selectRaw('personal_id')
            ->selectRaw('COUNT(*) as items')
            ->selectRaw('COALESCE(SUM(total), 0) as revenue')
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'servicio' THEN 1 ELSE 0 END), 0) as services")
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'producto' THEN 1 ELSE 0 END), 0) as products")
            ->groupBy('personal_id')
            ->get()
            ->keyBy('personal_id')
            ->map(fn (SaleItem $row) => [
                'items' => (int) $row->items,
                'revenue' => (float) $row->revenue,
                'services' => (int) $row->services,
                'products' => (int) $row->products,
            ]);
    }

    protected function commissionsByPerson(ReportPeriod $period): Collection
    {
        return CommissionEntry::whereBetween('earned_at', $period->range())
            ->selectRaw('personal_id')
            ->selectRaw('COALESCE(SUM(amount), 0) as total')
            ->groupBy('personal_id')
            ->pluck('total', 'personal_id');
    }

    /** La propina va en la venta, no en la línea: se atribuye al barbero de la venta. */
    protected function tipsByPerson(ReportPeriod $period): Collection
    {
        return Sale::paid()
            ->whereBetween('sold_at', $period->range())
            ->whereNotNull('personal_id')
            ->when($period->branchId, fn ($q) => $q->where('branch_id', $period->branchId))
            ->selectRaw('personal_id')
            ->selectRaw('COALESCE(SUM(tip), 0) as total')
            ->groupBy('personal_id')
            ->pluck('total', 'personal_id');
    }

    /** @return Collection<int, Collection<string, int>> */
    protected function appointmentsByPerson(ReportPeriod $period): Collection
    {
        return Appointment::whereBetween('starts_at', $period->range())
            ->when($period->branchId, fn ($q) => $q->where('branch_id', $period->branchId))
            ->selectRaw('personal_id, status, COUNT(*) as total')
            ->groupBy('personal_id', 'status')
            ->get()
            ->groupBy('personal_id')
            ->map(fn ($rows) => $rows->pluck('total', 'status'));
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
