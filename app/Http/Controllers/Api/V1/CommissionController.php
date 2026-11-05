<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Personal;
use App\Models\Sale;
use App\Support\CommissionSettler;
use App\Support\PendingCommissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Pagar comisiones desde la app (Gastos › Personal): lo que se le debe a
 * cada barbero, de cualquier fecha, y liquidarlo. Misma lógica que la web
 * (App\Support\CommissionSettler).
 */
class CommissionController extends ApiController
{
    /** Las comisiones sin pagar, por barbero, y el total. */
    public function pending(): JsonResponse
    {
        $rows = PendingCommissions::byPersonal();

        return $this->json([
            'summary' => PendingCommissions::summary($rows),
            'data' => $rows->map(function (array $r) {
                // Las propinas del mismo tramo: se pueden sumar al pago.
                $tips = (float) Sale::paid()->where('personal_id', $r['personal']->id)
                    ->whereBetween('sold_at', [$r['since']->copy()->startOfDay(), now()->endOfDay()])
                    ->sum('tip');

                return [
                    'personal_id' => $r['personal']->id,
                    'name' => $r['personal']->full_name,
                    'count' => $r['count'],
                    'total' => $r['total'],
                    'tips' => round($tips, 2),
                    'since' => $r['since']->toDateString(),
                ];
            })->values()->all(),
        ]);
    }

    /** Liquidar: period_start / period_end, include_tips, notes y de dónde sale el dinero. */
    public function settle(Request $request, Personal $personal, CommissionSettler $settler): JsonResponse
    {
        $settlement = $settler->fromRequest($request, $personal);

        return $this->json([
            'message' => "Liquidación {$settlement->number} pagada.",
            'data' => [
                'id' => $settlement->id,
                'number' => $settlement->number,
                'total' => (float) $settlement->total,
                'commissions' => (float) $settlement->commissions_total,
                'tips' => (float) $settlement->tips_total,
            ],
        ], 201);
    }
}
