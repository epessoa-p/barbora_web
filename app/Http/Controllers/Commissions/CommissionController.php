<?php

namespace App\Http\Controllers\Commissions;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Finance\ExpenseController;
use App\Models\CashSession;
use App\Models\ExpenseCategory;
use App\Support\CommissionSettler;
use App\Support\ExpenseRegistrar;
use App\Support\PendingCommissions;
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

        // Lo que falta pagar, de cualquier fecha: no depende del filtro.
        $owed = PendingCommissions::byPersonal();

        return view('commissions.index', [
            'from' => $from,
            'to' => $to,
            'rows' => $rows,
            'owed' => $owed,
            'owedSummary' => PendingCommissions::summary($owed),
            'hasRules' => \App\Models\CommissionRule::where('active', true)->exists(),
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

    /**
     * Liquidar: marca lo pendiente como pagado y saca el dinero de la cuenta o
     * la caja elegida (CommissionSettler, el mismo de la app).
     */
    public function settle(Request $request, Personal $personal, CommissionSettler $settler)
    {
        $settlement = $settler->fromRequest($request, $personal);

        return redirect()->route('commissions.settlements.show', $settlement)
            ->with('success', "Liquidación {$settlement->number} pagada: "
                . \App\Support\Money::format($settlement->total, $personal->company).'.');
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
