<?php

namespace App\Http\Controllers\Commissions;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Finance\ExpenseController;
use App\Models\CashSession;
use App\Models\ExpenseCategory;
use App\Support\ExpenseRegistrar;
use App\Support\PaymentSource;
use App\Models\CommissionEntry;
use App\Models\CommissionSettlement;
use App\Models\Personal;
use App\Models\Sale;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CommissionController extends Controller
{
    public function __construct(protected ExpenseRegistrar $expenses)
    {
    }

    /** Qué debe la barbería a cada barbero en el periodo. */
    public function index(Request $request)
    {
        [$from, $to] = $this->period($request);

        $staff = Personal::bookable()->orderBy('full_name')->get();

        $entries = CommissionEntry::with('personal')
            ->whereBetween('earned_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->get()
            ->groupBy('personal_id');

        // Las propinas no son comisión, pero se pagan en el mismo acto: se
        // muestran aparte para que el barbero vea de dónde sale cada importe.
        $tips = Sale::paid()
            ->whereBetween('sold_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->whereNotNull('personal_id')
            ->selectRaw('personal_id, SUM(tip) as total')
            ->groupBy('personal_id')
            ->pluck('total', 'personal_id');

        $rows = $staff->map(function (Personal $person) use ($entries, $tips) {
            $own = $entries->get($person->id, collect());
            $pending = $own->whereNull('commission_settlement_id');

            return [
                'personal' => $person,
                'entries' => $own->count(),
                'earned' => (float) $own->sum('amount'),
                'pending' => (float) $pending->sum('amount'),
                'pending_count' => $pending->count(),
                'tips' => (float) ($tips[$person->id] ?? 0),
            ];
        })->filter(fn (array $row) => $row['entries'] > 0 || $row['tips'] > 0)->values();

        return view('commissions.index', [
            'from' => $from,
            'to' => $to,
            'rows' => $rows,
            'totals' => [
                'earned' => $rows->sum('earned'),
                'pending' => $rows->sum('pending'),
                'tips' => $rows->sum('tips'),
            ],
        ]);
    }

    /** Detalle de un barbero: de dónde sale cada importe. */
    public function show(Request $request, Personal $personal)
    {
        [$from, $to] = $this->period($request);

        $entries = CommissionEntry::with(['sale', 'item', 'settlement'])
            ->where('personal_id', $personal->id)
            ->whereBetween('earned_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->orderByDesc('earned_at')
            ->get();

        $tips = (float) Sale::paid()
            ->where('personal_id', $personal->id)
            ->whereBetween('sold_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->sum('tip');

        return view('commissions.show', [
            'personal' => $personal,
            'from' => $from,
            'to' => $to,
            'entries' => $entries,
            'pending' => $entries->whereNull('commission_settlement_id'),
            'tips' => $tips,
            // De dónde sale el pago: una cuenta o la caja abierta, como un gasto.
            ...ExpenseController::sourceOptions($request->attributes->get('tenant_company')),
        ]);
    }

    /** Liquidar: marca lo pendiente como pagado y saca el dinero de caja. */
    public function settle(Request $request, Personal $personal)
    {
        $data = $request->validate([
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
            'include_tips' => 'sometimes|boolean',
            'notes' => 'nullable|string',
        ]);

        $from = Carbon::parse($data['period_start'])->startOfDay();
        $to = Carbon::parse($data['period_end'])->endOfDay();

        $pending = CommissionEntry::where('personal_id', $personal->id)
            ->pending()
            ->whereBetween('earned_at', [$from, $to])
            ->get();

        $includeTips = $request->boolean('include_tips');

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
        $source = $this->paymentSource($request, $company);

        $settlement = DB::transaction(function () use ($personal, $company, $pending, $commissions, $tips, $from, $to, $data, $source) {
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
                'paid_by' => auth()->id(),
                'notes' => $data['notes'] ?? null,
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
                ], auth()->id());

                $settlement->update(['cash_session_id' => $expense->cash_session_id]);
            }

            return $settlement;
        });

        return redirect()->route('commissions.settlements.show', $settlement)
            ->with('success', "Liquidación {$settlement->number} pagada: "
                . \App\Support\Money::format($settlement->total, $company).'.');
    }

    /**
     * De dónde sale el pago de la liquidación. Si el formulario lo dice (cuenta
     * o caja), eso; si no (una pantalla vieja), el primer turno de caja
     * abierto, como antes. Sin cuentas ni caja en el plan: de ninguna parte.
     *
     * @return array{0: string, 1: mixed, 2: mixed}|null
     */
    protected function paymentSource(Request $request, $company): ?array
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

    /**
     * Periodo consultado. Por defecto el mes en curso, que es el ciclo con el
     * que se paga en casi todas las barberías.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function period(Request $request): array
    {
        try {
            $from = $request->filled('from')
                ? Carbon::parse($request->query('from'))
                : Carbon::now()->startOfMonth();

            $to = $request->filled('to')
                ? Carbon::parse($request->query('to'))
                : Carbon::now()->endOfMonth();
        } catch (\Throwable) {
            $from = Carbon::now()->startOfMonth();
            $to = Carbon::now()->endOfMonth();
        }

        return [$from->startOfDay(), $to->startOfDay()];
    }
}
