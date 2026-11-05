<?php

namespace App\Support;

use App\Models\CashSession;
use App\Models\CommissionEntry;
use App\Models\CommissionSettlement;
use App\Models\ExpenseCategory;
use App\Models\Personal;
use App\Models\Sale;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Liquidar: pagarle a un barbero lo que lleva ganado en comisiones (y, si se
 * quiere, las propinas) de un periodo.
 *
 *   comisión     lo que gana por cada servicio o venta; se devenga sola al cobrar.
 *   liquidación  el pago de lo acumulado: un comprobante (L-000x) que marca esas
 *                comisiones como pagadas y sale como gasto «COMISIONES».
 *
 * Lo usan la web (Comisiones) y la API (la app, en Gastos › Personal).
 */
class CommissionSettler
{
    public function __construct(protected ExpenseRegistrar $expenses)
    {
    }

    /** Valida lo que llega del formulario o de la app y liquida. */
    public function fromRequest(Request $request, Personal $personal): CommissionSettlement
    {
        $data = $request->validate([
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
            'include_tips' => 'sometimes|boolean',
            'notes' => 'nullable|string',
        ]);

        return $this->settle(
            $personal,
            Carbon::parse($data['period_start'])->startOfDay(),
            Carbon::parse($data['period_end'])->endOfDay(),
            $request->boolean('include_tips'),
            $data['notes'] ?? null,
            $this->paymentSource($request, $personal->company),
            $request->user()?->id ?? auth()->id(),
        );
    }

    /** @param  array{0: string, 1: mixed, 2: mixed}|null  $source  de PaymentSource::validate() */
    public function settle(Personal $personal, Carbon $from, Carbon $to, bool $includeTips, ?string $notes, ?array $source, ?int $userId): CommissionSettlement
    {
        $pending = CommissionEntry::where('personal_id', $personal->id)
            ->pending()
            ->whereBetween('earned_at', [$from, $to])
            ->get();

        $tips = $includeTips
            ? (float) Sale::paid()
                ->where('personal_id', $personal->id)
                ->whereBetween('sold_at', [$from, $to])
                ->sum('tip')
            : 0.0;

        $commissions = (float) $pending->sum('amount');

        if ($pending->isEmpty() && $tips <= 0) {
            throw ValidationException::withMessages([
                'error' => 'No hay nada pendiente de liquidar en ese periodo.',
            ]);
        }

        $company = $personal->company;

        return DB::transaction(function () use ($personal, $company, $pending, $commissions, $tips, $from, $to, $notes, $source, $userId) {
            $total = round($commissions + $tips, 2);

            $settlement = CommissionSettlement::create([
                'company_id' => $company->id,
                'personal_id' => $personal->id,
                'number' => CommissionSettlement::nextNumberFor($company->id),
                'period_start' => $from->toDateString(),
                'period_end' => $to->toDateString(),
                'entries_count' => $pending->count(),
                'commissions_total' => $commissions,
                'tips_total' => $tips,
                'total' => $total,
                'paid_at' => now(),
                'paid_by' => $userId,
                'notes' => $notes,
            ]);

            CommissionEntry::whereIn('id', $pending->pluck('id'))
                ->update(['commission_settlement_id' => $settlement->id]);

            // El pago se registra como gasto («COMISIONES»), a nombre del
            // barbero, y sale de la cuenta o la caja elegida. Sin cuentas ni
            // caja en el plan, no hay de dónde sacarlo: queda solo la liquidación.
            if ($source) {
                $expense = $this->expenses->record($company, ExpenseCategory::system($company->id, ExpenseCategory::COMMISSIONS, 'personal'), $source, [
                    'branch_id' => $personal->branch_id ?? $source[2]?->branch_id ?? $source[1]?->branch_id,
                    'personal_id' => $personal->id,
                    'commission_settlement_id' => $settlement->id,
                    'description' => "Liquidación {$settlement->number} · {$personal->full_name}",
                    'amount' => $total,
                    'spent_on' => now()->toDateString(),
                    'reference' => $settlement->number,
                ], $userId);

                $settlement->update(['cash_session_id' => $expense->cash_session_id]);
            }

            return $settlement;
        });
    }

    /**
     * De dónde sale el pago de la liquidación. Si el formulario lo dice (cuenta
     * o caja), eso; si no (una pantalla vieja), el primer turno de caja
     * abierto, como antes. Sin cuentas ni caja en el plan: de ninguna parte.
     *
     * @return array{0: string, 1: mixed, 2: mixed}|null
     */
    public function paymentSource(Request $request, $company): ?array
    {
        if ($request->filled('source')) {
            return app(PaymentSource::class)->validate($request, $company);
        }

        if (! $company->planAllows('caja')) {
            return null;
        }

        $session = CashSession::open()->orderBy('opened_at')->first();

        if (! $session) {
            throw ValidationException::withMessages([
                'error' => 'No hay ningún turno de caja abierto. Ábrelo antes de pagar la liquidación.',
            ]);
        }

        return ['cash', null, $session];
    }
}
