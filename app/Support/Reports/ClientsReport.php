<?php

namespace App\Support\Reports;

use App\Models\Client;
use App\Models\Sale;
use App\Support\ReportPeriod;
use Illuminate\Support\Collection;

/** Nuevos, recurrentes y perdidos: la salud de la cartera. */
class ClientsReport
{
    /** Sin comprar en este plazo, se considera perdido. */
    public const LOST_AFTER_DAYS = 90;

    public function for(ReportPeriod $period): array
    {
        $activity = $this->activity($period);
        $newIds = $this->newClientIds($period);

        // «Recurrente» se mide dentro del periodo: vino más de una vez.
        $returning = $activity->filter(fn (array $row) => $row['visits'] > 1);
        $firstTimers = $activity->filter(fn (array $row) => in_array($row['client_id'], $newIds, true));

        return [
            'summary' => [
                'new' => count($newIds),
                'active' => $activity->count(),
                'returning' => $returning->count(),
                'first_timers' => $firstTimers->count(),
                'returning_rate' => $activity->count() > 0
                    ? round($returning->count() / $activity->count() * 100, 1)
                    : null,
                'revenue' => (float) $activity->sum('spent'),
                'total' => Client::where('active', true)->count(),
            ],
            'top' => $activity->sortByDesc('spent')->take(20)->values(),
            'lost' => $this->lost(),
        ];
    }

    /** Clientes que compraron en el periodo, con lo que gastaron. */
    public function activity(ReportPeriod $period): Collection
    {
        return Sale::paid()
            ->whereBetween('sold_at', $period->range())
            ->whereNotNull('client_id')
            ->when($period->branchId, fn ($q) => $q->where('branch_id', $period->branchId))
            ->with('client')
            ->selectRaw('client_id')
            ->selectRaw('COUNT(*) as visits')
            ->selectRaw('COALESCE(SUM(total), 0) as spent')
            ->selectRaw('MAX(sold_at) as last_visit')
            ->groupBy('client_id')
            ->get()
            ->map(fn (Sale $row) => [
                'client_id' => $row->client_id,
                'client' => $row->client?->full_name ?? 'Cliente eliminado',
                'phone' => $row->client?->phone,
                'visits' => (int) $row->visits,
                'spent' => (float) $row->spent,
                'average' => $row->visits > 0 ? round((float) $row->spent / (int) $row->visits, 2) : 0.0,
                'last_visit' => $row->last_visit,
            ])
            ->values();
    }

    /** Altas de clientes dentro del periodo. */
    public function newClientIds(ReportPeriod $period): array
    {
        return Client::whereBetween('created_at', $period->range())->pluck('id')->all();
    }

    /**
     * Clientes con alguna compra histórica pero ninguna en los últimos
     * LOST_AFTER_DAYS días. No depende del periodo elegido: «perdido» se mide
     * siempre contra hoy, o el dato no significaría nada.
     */
    public function lost(): Collection
    {
        $cutoff = now()->subDays(self::LOST_AFTER_DAYS);

        $lastSale = Sale::paid()
            ->whereNotNull('client_id')
            ->selectRaw('client_id')
            ->selectRaw('MAX(sold_at) as last_visit')
            ->selectRaw('COUNT(*) as visits')
            ->groupBy('client_id')
            ->get()
            ->keyBy('client_id');

        $stale = $lastSale->filter(fn ($row) => $row->last_visit < $cutoff->toDateTimeString());

        if ($stale->isEmpty()) {
            return collect();
        }

        return Client::whereIn('id', $stale->keys())
            ->where('active', true)
            ->get()
            ->map(fn (Client $client) => [
                'client' => $client->full_name,
                'phone' => $client->phone,
                'visits' => (int) $stale[$client->id]->visits,
                'last_visit' => $stale[$client->id]->last_visit,
            ])
            ->sortBy('last_visit')
            ->values();
    }
}
