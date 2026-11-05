<?php

namespace App\Support;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use Carbon\CarbonImmutable;

/**
 * El control de los servicios básicos de un mes: cuáles ya se pagaron y
 * cuáles faltan (luz, agua, internet, alquiler, el hosting…).
 *
 *   mensual  pagado si hubo un pago en ese mes;
 *   anual    pagado si hubo uno en los 12 meses que terminan ese mes, y se
 *            dice cuándo vuelve a tocar.
 *
 * El último pago (aunque sea de otro mes) va como referencia del importe,
 * para proponerlo al registrar el siguiente. Los gastos anulados no cuentan.
 */
class BasicServicesMonth
{
    public const MONTHS = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio',
        'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

    /** `$month` en formato YYYY-MM; inválido o vacío, el mes en curso. */
    public static function parse(?string $month): CarbonImmutable
    {
        try {
            return $month && preg_match('/^\d{4}-\d{2}$/', $month)
                ? CarbonImmutable::createFromFormat('!Y-m', $month)->startOfMonth()
                : CarbonImmutable::now()->startOfMonth();
        } catch (\Throwable) {
            return CarbonImmutable::now()->startOfMonth();
        }
    }

    public function for(CarbonImmutable $month): array
    {
        $start = $month->startOfMonth();
        $end = $month->endOfMonth();

        $categories = ExpenseCategory::active()->inGroup('basicos')->ordered()->get();

        // Todo lo necesario de una vez: los pagos hasta el fin del mes, desde
        // un año antes (lo más largo que mira un servicio anual).
        $expenses = Expense::query()->active()
            ->whereIn('expense_category_id', $categories->pluck('id'))
            ->whereDate('spent_on', '<=', $end->toDateString())
            ->orderByDesc('spent_on')->orderByDesc('id')
            ->get(['id', 'expense_category_id', 'amount', 'spent_on'])
            ->groupBy('expense_category_id');

        $rows = $categories->map(function (ExpenseCategory $category) use ($expenses, $start, $end) {
            $mine = $expenses->get($category->id, collect());
            $annual = $category->isAnnual();
            $from = $annual ? $end->subYear()->addDay()->startOfDay() : $start;

            $inWindow = $mine->filter(fn (Expense $e) => $e->spent_on->between($from, $end));
            $last = $mine->first();

            return [
                'id' => $category->id,
                'name' => $category->name,
                'frequency' => $category->frequency ?: 'mensual',
                'frequency_label' => ExpenseCategory::FREQUENCIES[$category->frequency] ?? 'Mensual',
                'status' => $inWindow->isNotEmpty() ? 'pagado' : 'pendiente',
                'paid' => round((float) $inWindow->sum('amount'), 2),
                'count' => $inWindow->count(),
                'last_paid_on' => $last?->spent_on->toDateString(),
                'last_amount' => $last ? (float) $last->amount : null,
                // Un anual pagado vuelve a tocar al año del último pago.
                'next_due_on' => $annual && $last ? $last->spent_on->copy()->addYear()->toDateString() : null,
            ];
        })->values();

        return [
            'month' => $start->format('Y-m'),
            'label' => ucfirst(self::MONTHS[$start->month - 1]).' '.$start->year,
            'previous' => $start->subMonth()->format('Y-m'),
            'next' => $start->addMonth()->lte(CarbonImmutable::now()->startOfMonth()) ? $start->addMonth()->format('Y-m') : null,
            'services' => $rows->all(),
            'paid_count' => $rows->where('status', 'pagado')->count(),
            'pending_count' => $rows->where('status', 'pendiente')->count(),
            'total_paid' => round((float) $rows->sum('paid'), 2),
        ];
    }
}
