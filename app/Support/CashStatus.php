<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\Caja;
use App\Models\CashSession;
use App\Models\Personal;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * El estado de las cajas para el botón «Caja» de la barra superior: cuáles
 * puede usar el usuario y si tienen el turno abierto.
 *
 * Cada caja abre y cierra su propio turno. La «preferida» es la misma que
 * muestra la pantalla de Caja: la asignada al personal del usuario y, si no
 * tiene una propia, la primera de sus sucursales. Por eso dos usuarios
 * pueden ver estados distintos: cada uno está mirando su caja.
 */
class CashStatus
{
    /**
     * @return array{cajas: Collection<int, array>, preferred: ?array}
     */
    public static function for(User $user, int $companyId): array
    {
        $cajas = Branch::limitToWorkable(Caja::with('branch')->where('active', true), 'branch_id', $user, $companyId)
            ->orderBy('name')->get();

        if ($cajas->isEmpty()) {
            return ['cajas' => collect(), 'preferred' => null];
        }

        $sessions = CashSession::with('openedBy')->open()->whereIn('caja_id', $cajas->pluck('id'))->get()->keyBy('caja_id');
        $myPersonalId = Personal::where('user_id', $user->id)->value('id');
        $preferredId = (($myPersonalId ? $cajas->firstWhere('personal_id', $myPersonalId) : null) ?? $cajas->first())->id;

        $rows = $cajas->map(function (Caja $caja) use ($sessions, $myPersonalId, $preferredId) {
            $session = $sessions->get($caja->id);
            $totals = $session?->totals();

            return [
                'id' => $caja->id,
                'name' => $caja->name,
                'branch' => $caja->branch?->name,
                'mine' => $myPersonalId !== null && $caja->personal_id === $myPersonalId,
                'preferred' => $caja->id === $preferredId,
                'open' => $session !== null,
                'balance' => (float) $caja->balance,
                'session' => $session ? [
                    'id' => $session->id,
                    'number' => $session->number,
                    'opened_by' => $session->openedBy?->name,
                    'opened_at' => $session->opened_at,
                    'expected_cash' => round($totals['expected_cash'], 2),
                    'income' => round($totals['income'], 2),
                ] : null,
            ];
        })
            // La preferida primero.
            ->sortByDesc('preferred')->values();

        return ['cajas' => $rows, 'preferred' => $rows->first()];
    }
}
