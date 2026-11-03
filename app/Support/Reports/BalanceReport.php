<?php

namespace App\Support\Reports;

use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\CommissionSettlement;
use App\Models\Expense;
use App\Models\FinanceMovement;
use App\Models\PaymentMethod;
use App\Models\PurchasePayment;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Support\ReportPeriod;
use Illuminate\Support\Collection;

/**
 * El balance de lo que entró y salió de verdad: caja (los turnos) y tesorería
 * (las cuentas), con cada movimiento clasificado.
 *
 * A diferencia de Ganancias (que mide lo vendido contra su costo), aquí se
 * cuenta el dinero: una compra pagada resta el día que se paga, aunque la
 * mercadería se venda después. Tres vistas:
 *
 *   results   ingresos y egresos por categoría y su utilidad, separando caja
 *             y tesorería. Los aportes y retiros del dueño van aparte: no son
 *             ganancia ni gasto.
 *   movements cada movimiento, para filtrarlo por origen y tipo.
 *   closings  los turnos de caja del periodo con su arqueo.
 *
 * Las transferencias entre cuentas y las anulaciones de tesorería no cuentan:
 * son dinero que se mueve de sitio, o que vuelve a donde estaba.
 */
class BalanceReport
{
    public const CATEGORIES = [
        'venta' => 'Venta',
        'otro_ingreso' => 'Otro ingreso',
        'anulacion_gasto' => 'Gasto anulado',
        'aporte' => 'Aporte de capital',
        'proveedor' => 'Pago a proveedor',
        'personal' => 'Pago a personal',
        'gasto' => 'Gasto',
        'devolucion_venta' => 'Devolución de venta',
        'gasto_operativo' => 'Gasto operativo',
        'retiro' => 'Retiro de capital',
    ];

    /** Dinero del dueño: ni ganancia ni gasto. */
    public const CAPITAL = ['aporte', 'retiro'];

    /** Hasta cuántos movimientos se listan; los totales cuentan todos. */
    public const LIST_LIMIT = 500;

    /** Orígenes que se pueden filtrar en Movimientos. */
    public const SOURCES = ['caja', 'tesoreria', 'todo'];

    /**
     * `$source`: caja (lo que cuadra con los cierres), tesoreria o todo.
     * `$type`: ingreso, egreso o null (ambos). Solo filtran Movimientos.
     */
    public function for(ReportPeriod $period, string $source = 'caja', ?string $type = null): array
    {
        $source = in_array($source, self::SOURCES, true) ? $source : 'caja';
        $type = in_array($type, ['ingreso', 'egreso'], true) ? $type : null;

        $movements = $this->cashMovements($period)
            ->merge($this->treasuryMovements($period))
            ->merge($this->salesWithoutCash($period))
            ->sortByDesc('at')
            ->values();

        return [
            'source' => $source,
            'type' => $type,
            'results' => $this->results($movements),
            'flow' => $this->flow($movements, $source, $type),
            'closings' => $this->closings($period),
        ];
    }

    /** Movimientos de un origen: totales, por categoría y la lista. */
    protected function flow(Collection $movements, string $source, ?string $type): array
    {
        // Las ventas cobradas sin turno cuentan como caja: es dinero del mostrador.
        $rows = $source === 'todo' ? $movements : $movements->filter(
            fn ($m) => $source === 'caja' ? $m['source'] !== 'tesoreria' : $m['source'] === 'tesoreria',
        );

        $income = round((float) $rows->where('type', 'ingreso')->sum('amount'), 2);
        $expense = round((float) $rows->where('type', 'egreso')->sum('amount'), 2);
        $listed = $type ? $rows->where('type', $type)->values() : $rows->values();

        return [
            'income' => $income,
            'expense' => $expense,
            'balance' => round($income - $expense, 2),
            'categories' => $listed->groupBy('category')
                ->map(fn ($group, $key) => [
                    'key' => $key,
                    'label' => self::CATEGORIES[$key] ?? $key,
                    'type' => $group->first()['type'],
                    'total' => round((float) $group->sum('amount'), 2),
                ])
                ->sortByDesc('total')
                ->values()
                ->all(),
            'items' => $listed->take(self::LIST_LIMIT)->all(),
            'count' => $listed->count(),
        ];
    }

    /* ── Resultados ───────────────────────────────────────────────────────── */

    protected function results(Collection $movements): array
    {
        $operating = $movements->reject(fn ($m) => in_array($m['category'], self::CAPITAL, true));

        $income = $operating->where('type', 'ingreso');
        $expense = $operating->where('type', 'egreso');

        // Lo cobrado por ventas se reparte entre servicios y productos según lo
        // que llevaba cada venta.
        $sales = $income->where('category', 'venta');
        $services = round((float) $sales->sum('services'), 2);
        $incomeLines = collect([
            ['key' => 'servicios', 'label' => 'Servicios', 'total' => $services],
            ['key' => 'productos', 'label' => 'Productos', 'total' => round((float) $sales->sum('amount') - $services, 2)],
        ])->merge($this->byCategory($income->where('category', '!=', 'venta')))
            ->filter(fn ($l) => $l['total'] > 0)
            ->values();

        $incomeTotal = round((float) $income->sum('amount'), 2);
        $expenseTotal = round((float) $expense->sum('amount'), 2);
        $capital = $movements->whereIn('category', self::CAPITAL);

        return [
            'income' => [
                'lines' => $incomeLines->all(),
                'total' => $incomeTotal,
                'by_source' => $this->bySource($income),
            ],
            'expense' => [
                'lines' => $this->byCategory($expense)->all(),
                'total' => $expenseTotal,
                'by_source' => $this->bySource($expense),
            ],
            'profit' => round($incomeTotal - $expenseTotal, 2),
            'capital' => [
                'contributions' => round((float) $capital->where('category', 'aporte')->sum('amount'), 2),
                'withdrawals' => round((float) $capital->where('category', 'retiro')->sum('amount'), 2),
            ],
        ];
    }

    protected function byCategory(Collection $rows): Collection
    {
        return $rows->groupBy('category')
            ->map(fn ($group, $key) => [
                'key' => $key,
                'label' => self::CATEGORIES[$key] ?? $key,
                'total' => round((float) $group->sum('amount'), 2),
            ])
            ->sortByDesc('total')
            ->values();
    }

    /** Cuánto de un total pasó por caja (o el mostrador) y cuánto por tesorería. */
    protected function bySource(Collection $rows): array
    {
        return [
            'caja' => round((float) $rows->where('source', '!=', 'tesoreria')->sum('amount'), 2),
            'tesoreria' => round((float) $rows->where('source', 'tesoreria')->sum('amount'), 2),
        ];
    }

    /* ── Movimientos ──────────────────────────────────────────────────────── */

    /** Lo que pasó por los turnos de caja, clasificado. */
    protected function cashMovements(ReportPeriod $period): Collection
    {
        $rows = CashMovement::with('session.caja', 'session.branch')
            ->whereBetween('created_at', $period->range())
            ->when($period->branchId, fn ($q) => $q->whereHas('session', fn ($s) => $s->where('branch_id', $period->branchId)))
            ->get();

        if ($rows->isEmpty()) {
            return collect();
        }

        $ids = $rows->pluck('id');
        $expenses = Expense::whereIn('cash_movement_id', $ids)->get(['cash_movement_id', 'personal_id', 'commission_settlement_id'])->keyBy('cash_movement_id');
        $purchases = PurchasePayment::whereIn('cash_movement_id', $ids)->pluck('cash_movement_id')->flip();

        // Ventas y liquidaciones se reconocen por su número en «reference»,
        // no por el concepto, que es texto libre.
        $references = $rows->pluck('reference')->filter()->unique();
        $sales = Sale::whereIn('number', $references)->pluck('id', 'number');
        $settlements = CommissionSettlement::whereIn('number', $references)->pluck('number')->flip();
        $serviceShare = $this->serviceShare($sales->values());
        $methods = PaymentMethod::withTrashed()->pluck('name', 'slug');

        return $rows->map(function (CashMovement $m) use ($expenses, $purchases, $sales, $settlements, $serviceShare, $methods) {
            $saleId = $m->reference ? ($sales[$m->reference] ?? null) : null;

            $category = $m->isIncome()
                ? match (true) {
                    $saleId !== null => 'venta',
                    str_starts_with((string) $m->concept, 'Anulación') => 'anulacion_gasto',
                    default => 'otro_ingreso',
                }
                : match (true) {
                    $expenses->has($m->id) => $this->expenseCategory($expenses[$m->id]),
                    $purchases->has($m->id) => 'proveedor',
                    $saleId !== null => 'devolucion_venta',
                    $m->reference && $settlements->has($m->reference) => 'personal',
                    default => 'gasto_operativo',
                };

            $session = $m->session;

            return $this->row('c'.$m->id, 'caja', $m->type, $category, $m->concept, (float) $m->amount, $m->created_at,
                collect([$session?->caja?->name, $session?->branch?->name])->filter()->implode(' · '),
                $methods[$m->payment_method] ?? $m->payment_method,
                $category === 'venta' ? round((float) $m->amount * ($serviceShare[$saleId] ?? 0), 2) : 0);
        });
    }

    /** Lo que pasó por las cuentas. Sin transferencias ni anulaciones. */
    protected function treasuryMovements(ReportPeriod $period): Collection
    {
        $rows = FinanceMovement::with('account')
            ->whereNull('cancelled_at')
            ->whereNotIn('origin', ['transferencia', 'anulacion'])
            ->whereDate('occurred_on', '>=', $period->from->toDateString())
            ->whereDate('occurred_on', '<=', $period->to->toDateString())
            ->when($period->branchId, fn ($q) => $q->whereHas('account', fn ($a) => $a->where('branch_id', $period->branchId)))
            ->get();

        if ($rows->isEmpty()) {
            return collect();
        }

        $expenses = Expense::whereIn('finance_movement_id', $rows->pluck('id'))
            ->get(['finance_movement_id', 'personal_id', 'commission_settlement_id'])->keyBy('finance_movement_id');

        return $rows->map(function (FinanceMovement $m) use ($expenses) {
            $category = match (true) {
                $m->origin === 'gasto' && $expenses->has($m->id) => $this->expenseCategory($expenses[$m->id]),
                $m->origin === 'gasto' => 'gasto',
                $m->origin === 'compra' => 'proveedor',
                $m->isIncome() => 'aporte',
                default => 'retiro',
            };

            // La fecha es la del movimiento; la hora, la de cuando se anotó.
            $at = $m->occurred_on->copy()->setTimeFrom($m->created_at ?? $m->occurred_on);

            return $this->row('t'.$m->id, 'tesoreria', $m->type, $category, $m->concept, (float) $m->amount, $at,
                $m->account?->name ?? '', null);
        });
    }

    /**
     * Ventas cobradas sin turno de caja (la barbería no usa caja, o se cobró
     * sin abrirla): el dinero entró igual. Las anuladas no cuentan.
     */
    protected function salesWithoutCash(ReportPeriod $period): Collection
    {
        $sales = Sale::with('payments', 'branch')
            ->whereNull('cash_session_id')
            ->where('status', '!=', 'anulada')
            ->whereBetween('sold_at', $period->range())
            ->when($period->branchId, fn ($q) => $q->where('branch_id', $period->branchId))
            ->get();

        if ($sales->isEmpty()) {
            return collect();
        }

        $share = $this->serviceShare($sales->pluck('id'));
        $methods = PaymentMethod::withTrashed()->pluck('name', 'slug');

        return $sales->flatMap(fn (Sale $sale) => $sale->payments->map(fn ($p) => $this->row(
            's'.$p->id, 'sin_caja', 'ingreso', 'venta', "Venta {$sale->number}", (float) $p->amount, $sale->sold_at,
            $sale->branch?->name ?? '', $methods[$p->payment_method] ?? $p->payment_method,
            round((float) $p->amount * ($share[$sale->id] ?? 0), 2),
        )));
    }

    /** Qué parte de cada venta (0–1) fue de servicios. */
    protected function serviceShare(Collection $saleIds): array
    {
        if ($saleIds->isEmpty()) {
            return [];
        }

        return SaleItem::whereIn('sale_id', $saleIds)
            ->selectRaw("sale_id, SUM(CASE WHEN type = 'servicio' THEN total ELSE 0 END) AS services, SUM(total) AS items")
            ->groupBy('sale_id')
            ->get()
            ->mapWithKeys(fn ($r) => [$r->sale_id => (float) $r->items > 0 ? (float) $r->services / (float) $r->items : 0])
            ->all();
    }

    protected function expenseCategory(Expense $expense): string
    {
        return $expense->commission_settlement_id || $expense->personal_id ? 'personal' : 'gasto';
    }

    protected function row(string $id, string $source, string $type, string $category, ?string $concept, float $amount, $at, string $place, ?string $method, float $services = 0): array
    {
        return [
            'id' => $id,
            'source' => $source,
            'type' => $type,
            'category' => $category,
            'category_label' => self::CATEGORIES[$category],
            'concept' => (string) $concept,
            'amount' => round($amount, 2),
            'at' => $at->toIso8601String(),
            'place' => $place,
            'method' => $method,
            // Solo en ventas: lo que corresponde a servicios.
            'services' => $services,
        ];
    }

    /* ── Cierres ──────────────────────────────────────────────────────────── */

    /** Los turnos que se abrieron o cerraron en el periodo, y los abiertos. */
    protected function closings(ReportPeriod $period): array
    {
        $cash = PaymentMethod::cashSlugs();
        $methods = PaymentMethod::withTrashed()->pluck('name', 'slug');

        return CashSession::with(['caja', 'branch', 'openedBy', 'closedBy', 'movements'])
            ->where(fn ($q) => $q->whereBetween('opened_at', $period->range())
                ->orWhereBetween('closed_at', $period->range())
                ->orWhere('status', 'abierta'))
            ->when($period->branchId, fn ($q) => $q->where('branch_id', $period->branchId))
            ->orderByDesc('opened_at')
            ->limit(200)
            ->get()
            ->map(function (CashSession $s) use ($cash, $methods) {
                $t = $s->totals();
                $open = $s->isOpen();

                // Lo que no es efectivo no está en el cajón: va aparte.
                $others = $s->movements->whereNotIn('payment_method', $cash)
                    ->groupBy('payment_method')
                    ->map(fn ($rows, $slug) => [
                        'label' => $methods[$slug] ?? $slug,
                        'amount' => round((float) $rows->sum(fn ($m) => $m->signedAmount()), 2),
                    ])
                    ->values()
                    ->all();

                return [
                    'id' => $s->id,
                    'number' => $s->number,
                    'caja' => $s->caja?->name,
                    'branch' => $s->branch?->name,
                    'status' => $open ? 'abierta' : 'cerrada',
                    'opened_at' => $s->opened_at?->toIso8601String(),
                    'opened_by' => $s->openedBy?->name,
                    'closed_at' => $s->closed_at?->toIso8601String(),
                    'closed_by' => $s->closedBy?->name,
                    'opening' => round((float) $s->opening_amount, 2),
                    'cash_income' => round($t['cash_income'], 2),
                    'cash_expense' => round($t['cash_expense'], 2),
                    'expected' => round($open ? $t['expected_cash'] : (float) $s->expected_amount, 2),
                    'counted' => $open ? null : round((float) $s->closing_amount, 2),
                    'difference' => $open ? null : round((float) $s->difference, 2),
                    'other_methods' => $others,
                ];
            })
            ->all();
    }
}
