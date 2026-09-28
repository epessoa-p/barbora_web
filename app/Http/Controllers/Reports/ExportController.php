<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Support\ReportPeriod;
use App\Support\Reports\ClientsReport;
use App\Support\Reports\EarningsReport;
use App\Support\Reports\ProductsReport;
use App\Support\Reports\SalesReport;
use App\Support\Reports\ServicesReport;
use App\Support\Reports\StaffReport;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Descarga de un reporte en CSV.
 *
 * Se genera para que Excel en español lo abra de un doble clic: BOM UTF-8 (si
 * no, se comen los acentos), punto y coma como separador y coma decimal, que es
 * lo que espera esa configuración regional. Para PDF, cada reporte tiene su
 * vista de impresión (?print=1), que el navegador guarda como PDF sin que el
 * proyecto cargue con una librería de render.
 */
class ExportController extends Controller
{
    public function __invoke(Request $request, string $report): StreamedResponse
    {
        $period = ReportPeriod::fromRequest($request);

        [$headers, $rows] = match ($report) {
            'ventas' => $this->sales($period),
            'servicios' => $this->services($period),
            'barberos' => $this->staff($period),
            'productos' => $this->products($period),
            'clientes' => $this->clients($period),
            'ganancias' => $this->earnings($period),
            default => abort(404),
        };

        $filename = sprintf(
            'barbora-%s-%s-a-%s.csv',
            $report,
            $period->from->toDateString(),
            $period->to->toDateString(),
        );

        return response()->streamDownload(
            fn () => $this->write($headers, $rows),
            $filename,
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    protected function write(array $headers, array $rows): void
    {
        $out = fopen('php://output', 'w');

        // Sin el BOM, Excel abre el archivo como Latin-1 y rompe los acentos.
        fwrite($out, chr(0xEF).chr(0xBB).chr(0xBF));

        fputcsv($out, $headers, ';');

        foreach ($rows as $row) {
            fputcsv($out, array_map([$this, 'cell'], $row), ';');
        }

        fclose($out);
    }

    /** Los decimales van con coma: es lo que Excel en español entiende como número. */
    protected function cell(mixed $value): string
    {
        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            return str_replace('.', ',', number_format($value, 2, '.', ''));
        }

        return (string) ($value ?? '');
    }

    protected function sales(ReportPeriod $period): array
    {
        $rows = app(SalesReport::class)->daily($period)
            ->map(fn (array $day) => [$day['date'], $day['sales'], $day['total']])
            ->all();

        return [['Fecha', 'Ventas', 'Total'], $rows];
    }

    protected function services(ReportPeriod $period): array
    {
        $rows = app(ServicesReport::class)->ranking($period)
            ->map(fn (array $r) => [$r['service'], $r['times'], $r['quantity'], $r['revenue'], $r['average']])
            ->all();

        return [['Servicio', 'Veces', 'Cantidad', 'Ingreso', 'Promedio'], $rows];
    }

    protected function staff(ReportPeriod $period): array
    {
        $rows = app(StaffReport::class)->ranking($period)
            ->map(fn (array $r) => [
                $r['personal']->full_name,
                $r['services'],
                $r['products'],
                $r['revenue'],
                $r['commissions'],
                $r['tips'],
                $r['attended'],
                $r['no_show'],
            ])
            ->all();

        return [
            ['Barbero', 'Servicios', 'Productos', 'Facturado', 'Comisiones', 'Propinas', 'Citas atendidas', 'No show'],
            $rows,
        ];
    }

    protected function products(ReportPeriod $period): array
    {
        $rows = app(ProductsReport::class)->ranking($period)
            ->map(fn (array $r) => [
                $r['product'], $r['quantity'], $r['unit'], $r['revenue'], $r['cost'], $r['margin'],
            ])
            ->all();

        return [['Producto', 'Cantidad', 'Unidad', 'Ingreso', 'Costo', 'Margen'], $rows];
    }

    protected function clients(ReportPeriod $period): array
    {
        $rows = app(ClientsReport::class)->activity($period)
            ->sortByDesc('spent')
            ->map(fn (array $r) => [$r['client'], $r['phone'], $r['visits'], $r['spent'], $r['average']])
            ->values()
            ->all();

        return [['Cliente', 'Teléfono', 'Visitas', 'Gastado', 'Promedio'], $rows];
    }

    protected function earnings(ReportPeriod $period): array
    {
        $e = app(EarningsReport::class)->for($period);

        $rows = [
            ['Ingreso por ventas', $e['revenue']],
            ['Costo de productos', -$e['product_cost']],
            ['Margen bruto', $e['gross']],
            ['Comisiones devengadas', -$e['commissions']],
            ['Otros egresos de caja', -$e['other_expenses']],
            ['Resultado', $e['net']],
            ['Propinas (pasan al barbero)', $e['tips']],
            ['Descuentos aplicados', $e['discount']],
        ];

        return [['Concepto', 'Importe'], $rows];
    }
}
