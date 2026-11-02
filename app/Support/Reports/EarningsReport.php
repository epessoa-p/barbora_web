<?php

namespace App\Support\Reports;

use App\Models\CashMovement;
use App\Models\CommissionEntry;
use App\Models\CommissionSettlement;
use App\Models\Expense;
use App\Models\PurchasePayment;
use App\Models\Sale;
use App\Support\ReportPeriod;

/**
 * De lo cobrado a lo que queda.
 *
 * Es una cifra de gestión, no contabilidad: sirve para decidir, no para
 * declarar impuestos. Resta los gastos registrados (alquiler, luz, sueldos…,
 * salgan de una cuenta o de la caja) y los egresos sueltos de caja.
 *
 * El costo de los productos sale de `sale_items.unit_cost`, congelado el día de
 * la venta, así que el resultado de un mes cerrado ya no se mueve. Las ventas
 * anteriores a que se guardara ese dato se estiman con el costo actual, y en
 * ese caso «estimated» viene en verdadero para que la vista lo avise.
 */
class EarningsReport
{
    public function __construct(
        protected SalesReport $sales,
        protected ProductsReport $products,
    ) {
    }

    public function for(ReportPeriod $period): array
    {
        $totals = $this->sales->totals($period);
        $productRows = $this->products->ranking($period);

        $revenue = (float) $totals['total'] - (float) $totals['tip'];
        $productCost = (float) $productRows->sum('cost');
        $commissions = $this->commissions($period);
        $expensesByCategory = $this->expensesByCategory($period);
        $looseCash = $this->looseCashExpenses($period);
        $otherExpenses = round($expensesByCategory->sum('total') + $looseCash, 2);

        $gross = round($revenue - $productCost, 2);
        $net = round($gross - $commissions - $otherExpenses, 2);

        return [
            'revenue' => round($revenue, 2),
            'tips' => (float) $totals['tip'],
            'discount' => (float) $totals['discount'],
            'product_cost' => $productCost,
            'commissions' => $commissions,
            'other_expenses' => $otherExpenses,
            // El detalle de «otros gastos»: los gastos por categoría y los
            // egresos de caja que no son un gasto registrado.
            'expenses_by_category' => $expensesByCategory->all(),
            'loose_cash_expenses' => $looseCash,
            'gross' => $gross,
            'net' => $net,
            'margin_pct' => $revenue > 0 ? round($net / $revenue * 100, 1) : null,
            'sales' => (int) $totals['sales'],
            'ticket' => (float) $totals['ticket'],
            'daily' => $this->sales->daily($period),
            // Verdadero solo si alguna línea de producto no tenía el costo
            // guardado y hubo que suponerlo.
            'estimated' => $productRows->contains('estimated', true),
        ];
    }

    /** Comisiones devengadas en el periodo, estén liquidadas o no. */
    protected function commissions(ReportPeriod $period): float
    {
        return (float) CommissionEntry::whereBetween('earned_at', $period->range())->sum('amount');
    }

    /**
     * Los gastos registrados del periodo (de cuentas y de caja), por categoría.
     *
     * Las liquidaciones de comisión NO entran: ya restan como «comisiones
     * devengadas», y contarlas aquí sería descontarlas dos veces. Las compras
     * de mercadería tampoco son gasto: restan como «costo de productos» cuando
     * se venden.
     *
     * @return \Illuminate\Support\Collection<int, array{category: string, total: float, count: int}>
     */
    protected function expensesByCategory(ReportPeriod $period)
    {
        return Expense::query()
            ->active()
            ->whereNull('commission_settlement_id')
            ->whereDate('spent_on', '>=', $period->from->toDateString())
            ->whereDate('spent_on', '<=', $period->to->toDateString())
            ->when($period->branchId, fn ($q) => $q->where('branch_id', $period->branchId))
            ->with('category')
            ->get()
            ->groupBy(fn (Expense $e) => $e->category?->name ?? 'OTROS')
            ->map(fn ($rows, $category) => [
                'category' => $category,
                'total' => round((float) $rows->sum('amount'), 2),
                'count' => $rows->count(),
            ])
            ->sortByDesc('total')
            ->values();
    }

    /**
     * Egresos de caja «sueltos»: los que se registraron directamente en el
     * turno (un retiro, algo pagado del cajón) y no son un gasto registrado.
     *
     * Se dejan fuera los que ya están contados en otro sitio:
     *   · los de gastos registrados (cuentan arriba, por categoría);
     *   · los pagos de compras (la mercadería resta como costo al venderse);
     *   · las liquidaciones de comisión (restan como comisiones);
     *   · las anulaciones de venta (su venta ya quedó fuera del ingreso).
     *
     * Las dos últimas se reconocen por su «reference» (el número de la venta o
     * de la liquidación), no por el concepto, que es texto libre.
     */
    protected function looseCashExpenses(ReportPeriod $period): float
    {
        $expenses = CashMovement::whereBetween('created_at', $period->range())
            ->where('type', 'egreso')
            ->when($period->branchId, fn ($q) => $q->whereHas('session', fn ($s) => $s->where('branch_id', $period->branchId)))
            ->whereNotIn('id', Expense::query()->whereNotNull('cash_movement_id')->select('cash_movement_id'))
            ->whereNotIn('id', PurchasePayment::query()->whereNotNull('cash_movement_id')->select('cash_movement_id'));

        $references = (clone $expenses)->pluck('reference')->filter()->unique();

        if ($references->isEmpty()) {
            return round((float) $expenses->sum('amount'), 2);
        }

        $systemRefs = Sale::whereIn('number', $references)->pluck('number')
            ->merge(CommissionSettlement::whereIn('number', $references)->pluck('number'))
            ->unique();

        if ($systemRefs->isEmpty()) {
            return round((float) $expenses->sum('amount'), 2);
        }

        return round((float) $expenses
            ->where(fn ($q) => $q->whereNull('reference')->orWhereNotIn('reference', $systemRefs))
            ->sum('amount'), 2);
    }
}
