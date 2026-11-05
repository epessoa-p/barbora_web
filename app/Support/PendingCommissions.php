<?php

namespace App\Support;

use App\Models\CommissionEntry;
use App\Models\Personal;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Lo que la barbería debe en comisiones, sin importar las fechas: todas las
 * comisiones devengadas que todavía no entraron en una liquidación.
 *
 * Es lo que se muestra en «Pendiente de pagar» (Comisiones, Gastos ›
 * Personal y la app), para no depender del filtro de fechas: una comisión del
 * mes pasado sin pagar tiene que verse igual.
 */
class PendingCommissions
{
    /**
     * Por barbero: cuántas líneas, cuánto y desde cuándo.
     *
     * @return Collection<int, array{personal: Personal, count: int, total: float, since: Carbon, until: Carbon}>
     */
    public static function byPersonal(): Collection
    {
        $rows = CommissionEntry::query()->pending()
            ->selectRaw('personal_id, COUNT(*) as entries, SUM(amount) as total, MIN(earned_at) as since, MAX(earned_at) as until')
            ->groupBy('personal_id')
            ->get();

        $staff = Personal::whereIn('id', $rows->pluck('personal_id'))->get()->keyBy('id');

        return $rows
            ->filter(fn ($r) => $staff->has($r->personal_id) && (float) $r->total > 0)
            ->map(fn ($r) => [
                'personal' => $staff[$r->personal_id],
                'count' => (int) $r->entries,
                'total' => round((float) $r->total, 2),
                'since' => Carbon::parse($r->since),
                'until' => Carbon::parse($r->until),
            ])
            ->sortByDesc('total')
            ->values();
    }

    /** @return array{count: int, total: float, people: int} */
    public static function summary(?Collection $rows = null): array
    {
        $rows ??= self::byPersonal();

        return [
            'count' => (int) $rows->sum('count'),
            'total' => round((float) $rows->sum('total'), 2),
            'people' => $rows->count(),
        ];
    }
}
