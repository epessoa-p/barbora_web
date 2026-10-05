<?php

namespace App\Http\Controllers\Api\V1;

use App\Support\ReportPeriod;
use App\Support\Reports\ClientsReport;
use App\Support\Reports\EarningsReport;
use App\Support\Reports\ProductsReport;
use App\Support\Reports\SalesReport;
use App\Support\Reports\ServicesReport;
use App\Support\Reports\StaffReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Los reportes, para verlos desde el móvil.
 *
 * Reusa el mismo cálculo que la web (App\Support\Reports\*), y lo traduce a un
 * formato uniforme —resumen de tarjetas + tablas— para que la app pinte todos
 * los reportes con una sola pantalla. Los importes van como número: la app les
 * pone el símbolo de la barbería.
 */
class ReportController extends ApiController
{
    /** Tipos válidos y su título. El móvil pinta una tarjeta por cada uno. */
    public const TYPES = [
        'ventas' => 'Ventas',
        'servicios' => 'Servicios',
        'barberos' => 'Barberos',
        'productos' => 'Productos',
        'clientes' => 'Clientes',
        'ganancias' => 'Ganancias',
    ];

    public function show(Request $request, string $type): JsonResponse
    {
        if (! isset(self::TYPES[$type])) {
            return $this->json(['error' => 'not_found', 'message' => 'Reporte desconocido.'], 404);
        }

        $period = ReportPeriod::fromRequest($request);

        $payload = match ($type) {
            'ventas' => $this->sales($period),
            'servicios' => $this->services($period),
            'barberos' => $this->staff($period),
            'productos' => $this->products($period),
            'clientes' => $this->clients($period),
            'ganancias' => $this->earnings($period),
        };

        return $this->json([
            'key' => $type,
            'title' => self::TYPES[$type],
            'period' => $this->period($request, $period),
            'summary' => $payload['summary'],
            'tables' => $payload['tables'],
            'note' => $payload['note'] ?? null,
        ]);
    }

    /* ── Reportes ─────────────────────────────────────────────────────────── */

    protected function sales(ReportPeriod $period): array
    {
        $r = app(SalesReport::class)->for($period);
        $t = $r['totals'];

        return [
            'summary' => [
                $this->stat('Ventas', $t['sales'], 'int'),
                $this->stat('Total', $t['total'], 'money'),
                $this->stat('Ticket medio', $t['ticket'], 'money'),
                $this->stat('Propinas', $t['tip'], 'money'),
                $this->stat('Descuentos', $t['discount'], 'money'),
                $this->stat('Anuladas', $t['cancelled'], 'int'),
            ],
            'tables' => [
                $this->table('Por método de pago',
                    [$this->col('Método'), $this->col('Cobros', 'int', 'end'), $this->col('Total', 'money', 'end')],
                    collect($r['byMethod'])->map(fn ($x) => [$x['method'], $x['payments'], $x['total']])),
                $this->table('Por sucursal',
                    [$this->col('Sucursal'), $this->col('Ventas', 'int', 'end'), $this->col('Total', 'money', 'end')],
                    collect($r['byBranch'])->map(fn ($x) => [$x['branch'], $x['sales'], $x['total']])),
                $this->table('Por día',
                    [$this->col('Día'), $this->col('Ventas', 'int', 'end'), $this->col('Total', 'money', 'end')],
                    collect($r['daily'])->where('sales', '>', 0)->map(fn ($x) => [$x['label'], $x['sales'], $x['total']])),
            ],
        ];
    }

    protected function services(ReportPeriod $period): array
    {
        $r = app(ServicesReport::class)->for($period);
        $a = $r['appointments'];

        return [
            'summary' => [
                $this->stat('Servicios', $r['totals']['services'], 'int'),
                $this->stat('Cantidad', $r['totals']['quantity'], 'int'),
                $this->stat('Ingresos', $r['totals']['revenue'], 'money'),
                $this->stat('Citas', $a['total'], 'int'),
                $this->stat('Atendidas', $a['attended'], 'int'),
                $this->stat('No-show', $a['no_show'], 'int'),
                $this->stat('Asistencia', $a['attendance_rate'], 'percent'),
            ],
            'tables' => [
                $this->table('Ranking de servicios',
                    [$this->col('Servicio'), $this->col('Veces', 'int', 'end'), $this->col('Ingresos', 'money', 'end'), $this->col('Promedio', 'money', 'end')],
                    collect($r['rows'])->map(fn ($x) => [$x['service'], $x['times'], $x['revenue'], $x['average']])),
            ],
        ];
    }

    protected function staff(ReportPeriod $period): array
    {
        $r = app(StaffReport::class)->for($period);

        return [
            'summary' => [
                $this->stat('Ingresos', $r['totals']['revenue'], 'money'),
                $this->stat('Comisiones', $r['totals']['commissions'], 'money'),
                $this->stat('Propinas', $r['totals']['tips'], 'money'),
                $this->stat('Atendidas', $r['totals']['attended'], 'int'),
            ],
            'tables' => [
                $this->table('Por barbero',
                    [$this->col('Barbero'), $this->col('Ingresos', 'money', 'end'), $this->col('Comis.', 'money', 'end'), $this->col('Prop.', 'money', 'end'), $this->col('Atend.', 'int', 'end')],
                    collect($r['rows'])->map(fn ($x) => [
                        $x['personal']->full_name ?? '—',
                        $x['revenue'], $x['commissions'], $x['tips'], $x['attended'],
                    ])),
            ],
        ];
    }

    protected function products(ReportPeriod $period): array
    {
        $r = app(ProductsReport::class)->for($period);

        return [
            'note' => $r['estimated']
                ? 'Algún costo es estimado (ventas antiguas sin costo congelado): el margen es aproximado.'
                : null,
            'summary' => [
                $this->stat('Cantidad', $r['totals']['quantity'], 'int'),
                $this->stat('Ingresos', $r['totals']['revenue'], 'money'),
                $this->stat('Costo', $r['totals']['cost'], 'money'),
                $this->stat('Margen', $r['totals']['margin'], 'money'),
            ],
            'tables' => [
                $this->table('Ranking de productos',
                    [$this->col('Producto'), $this->col('Cant.', 'int', 'end'), $this->col('Ingresos', 'money', 'end'), $this->col('Margen', 'money', 'end')],
                    collect($r['rows'])->map(fn ($x) => [$x['product'], $x['quantity'], $x['revenue'], $x['margin']])),
                $this->table('Stock bajo',
                    [$this->col('Producto'), $this->col('Existencia', 'int', 'end')],
                    collect($r['lowStock'])->map(fn ($s) => [$s->product?->name ?? '—', (float) $s->quantity])),
            ],
        ];
    }

    protected function clients(ReportPeriod $period): array
    {
        $r = app(ClientsReport::class)->for($period);
        $s = $r['summary'];

        return [
            'summary' => [
                $this->stat('Nuevos', $s['new'], 'int'),
                $this->stat('Activos', $s['active'], 'int'),
                $this->stat('Recurrentes', $s['returning'], 'int'),
                $this->stat('Recurrencia', $s['returning_rate'], 'percent'),
                $this->stat('Ingresos', $s['revenue'], 'money'),
                $this->stat('Clientes', $s['total'], 'int'),
            ],
            'tables' => [
                $this->table('Top clientes',
                    [$this->col('Cliente'), $this->col('Visitas', 'int', 'end'), $this->col('Gasto', 'money', 'end')],
                    collect($r['top'])->map(fn ($x) => [$x['client'], $x['visits'], $x['spent']])),
            ],
        ];
    }

    protected function earnings(ReportPeriod $period): array
    {
        $r = app(EarningsReport::class)->for($period);

        return [
            'note' => ($r['estimated'] ?? false)
                ? 'El costo de productos incluye estimaciones; el neto es aproximado.'
                : null,
            'summary' => [
                $this->stat('Ingresos', $r['revenue'], 'money'),
                $this->stat('Costo productos', $r['product_cost'], 'money'),
                $this->stat('Comisiones', $r['commissions'], 'money'),
                $this->stat('Otros gastos', $r['other_expenses'], 'money'),
                $this->stat('Bruto', $r['gross'], 'money'),
                $this->stat('Neto', $r['net'], 'money'),
                $this->stat('Margen', $r['margin_pct'], 'percent'),
                $this->stat('Propinas', $r['tips'], 'money'),
            ],
            'tables' => [],
        ];
    }

    /* ── Armado ───────────────────────────────────────────────────────────── */

    protected function period(Request $request, ReportPeriod $period): array
    {
        $preset = $request->input('preset');
        $label = $preset && isset(ReportPeriod::PRESETS[$preset])
            ? ReportPeriod::PRESETS[$preset]
            : $period->from->translatedFormat('d/m/Y').' – '.$period->to->translatedFormat('d/m/Y');

        return [
            'preset' => is_string($preset) && isset(ReportPeriod::PRESETS[$preset]) ? $preset : null,
            'label' => $label,
            'from' => $period->from->toDateString(),
            'to' => $period->to->toDateString(),
            'presets' => collect(ReportPeriod::PRESETS)->map(fn ($l, $k) => ['key' => $k, 'label' => $l])->values()->all(),
        ];
    }

    protected function stat(string $label, $value, string $kind): array
    {
        return ['label' => $label, 'value' => $value, 'kind' => $kind];
    }

    protected function col(string $label, string $kind = 'text', string $align = 'start'): array
    {
        return ['label' => $label, 'kind' => $kind, 'align' => $align];
    }

    /** @param  \Illuminate\Support\Collection<int, array>|array  $rows */
    protected function table(string $title, array $columns, $rows): array
    {
        return [
            'title' => $title,
            'columns' => $columns,
            'rows' => collect($rows)->values()->all(),
        ];
    }
}
