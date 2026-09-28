<?php

namespace App\Support\Reports;

use App\Models\CashMovement;
use App\Models\CommissionEntry;
use App\Models\CommissionSettlement;
use App\Models\Sale;
use App\Support\ReportPeriod;

/**
 * De lo cobrado a lo que queda.
 *
 * Es una cifra de gestión, no contabilidad: no contempla alquiler, sueldos
 * fijos ni servicios. Sirve para decidir, no para declarar impuestos.
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
        $otherExpenses = $this->otherExpenses($period);

        $gross = round($revenue - $productCost, 2);
        $net = round($gross - $commissions - $otherExpenses, 2);

        return [
            'revenue' => round($revenue, 2),
            'tips' => (float) $totals['tip'],
            'discount' => (float) $totals['discount'],
            'product_cost' => $productCost,
            'commissions' => $commissions,
            'other_expenses' => $otherExpenses,
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
     * Egresos de caja propios del negocio: compras, retiros, gastos del día.
     *
     * Se descuentan dos clases de egreso que el sistema genera solo y que ya
     * están contadas en otro sitio:
     *   · las liquidaciones de comisión, que se cuentan como comisiones;
     *   · las anulaciones de venta, cuya venta ya quedó fuera del ingreso.
     *
     * Ambas se reconocen por su «reference», que es el número de la venta o de
     * la liquidación, en vez de por el texto del concepto, que es libre.
     */
    protected function otherExpenses(ReportPeriod $period): float
    {
        $expenses = CashMovement::whereBetween('created_at', $period->range())
            ->where('type', 'egreso');

        $references = (clone $expenses)->pluck('reference')->filter()->unique();

        if ($references->isEmpty()) {
            return (float) $expenses->sum('amount');
        }

        $systemRefs = Sale::whereIn('number', $references)->pluck('number')
            ->merge(CommissionSettlement::whereIn('number', $references)->pluck('number'))
            ->unique();

        if ($systemRefs->isEmpty()) {
            return (float) $expenses->sum('amount');
        }

        return (float) $expenses
            ->where(fn ($q) => $q->whereNull('reference')->orWhereNotIn('reference', $systemRefs))
            ->sum('amount');
    }
}
