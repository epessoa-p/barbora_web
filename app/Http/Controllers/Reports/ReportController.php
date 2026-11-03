<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Support\ReportPeriod;
use App\Support\Reports\AnalysisReport;
use App\Support\Reports\BalanceReport;
use App\Support\Reports\ClientsReport;
use App\Support\Reports\EarningsReport;
use App\Support\Reports\ProductsReport;
use App\Support\Reports\ProfitReport;
use App\Support\Reports\SalesReport;
use App\Support\Reports\ServicesReport;
use App\Support\Reports\StaffReport;
use App\Support\Tenancy;
use Illuminate\Http\Request;

/**
 * Reportes del negocio. Módulo de plan «estadisticas».
 *
 * Todo es de solo lectura: cada método arma el periodo, delega la consulta a su
 * clase de App\Support\Reports y pinta. La lógica no vive aquí para que la
 * pueda reutilizar tal cual el dashboard y, más adelante, la API móvil.
 */
class ReportController extends Controller
{
    /** Portada: desde dónde se llega a cada reporte. */
    public function index(Request $request)
    {
        $period = ReportPeriod::fromRequest($request);

        return view('reports.index', $this->common($period) + [
            'summary' => app(SalesReport::class)->totals($period),
        ]);
    }

    public function sales(Request $request, SalesReport $report)
    {
        $period = ReportPeriod::fromRequest($request);

        return view('reports.sales', $this->common($period) + $report->for($period));
    }

    public function services(Request $request, ServicesReport $report)
    {
        $period = ReportPeriod::fromRequest($request);

        return view('reports.services', $this->common($period) + $report->for($period));
    }

    public function staff(Request $request, StaffReport $report)
    {
        $period = ReportPeriod::fromRequest($request);

        return view('reports.staff', $this->common($period) + $report->for($period));
    }

    public function products(Request $request, ProductsReport $report)
    {
        $period = ReportPeriod::fromRequest($request);

        return view('reports.products', $this->common($period) + $report->for($period));
    }

    public function clients(Request $request, ClientsReport $report)
    {
        $period = ReportPeriod::fromRequest($request);

        return view('reports.clients', $this->common($period) + $report->for($period));
    }

    public function earnings(Request $request, EarningsReport $report)
    {
        $period = ReportPeriod::fromRequest($request);

        return view('reports.earnings', $this->common($period) + [
            'earnings' => $report->for($period),
            // Ganancia por lo vendido: Ambos / Ventas / Servicios, y si se unen
            // las ventas rápidas (sin costo conocido).
            'profit' => app(ProfitReport::class)->for($period, (string) $request->query('scope', 'ambos'), $request->boolean('quick')),
        ]);
    }

    /** Balance del dinero: Resultados, Movimientos (caja/tesorería) y Cierres de caja. */
    public function balance(Request $request, BalanceReport $report)
    {
        $period = ReportPeriod::fromRequest($request);
        $tab = in_array($request->query('tab'), ['movimientos', 'cierres'], true) ? $request->query('tab') : 'resultados';

        return view('reports.balance', $this->common($period) + [
            'tab' => $tab,
            'balance' => $report->for($period, (string) $request->query('source', 'caja'), $request->query('type')),
        ]);
    }

    /**
     * Análisis: semana y mes contra el anterior, por día, por semana y por
     * mes; Servicios / Ventas / Compras / Top, en monto o cantidad.
     */
    public function analysis(Request $request, AnalysisReport $report)
    {
        $period = ReportPeriod::fromRequest($request);
        // Compras es del módulo «inventario»: sin él, no hay pestaña.
        $withPurchases = (bool) app(Tenancy::class)->company()?->planAllows('inventario');

        $tabs = array_keys(AnalysisReport::SERIES);
        if (! $withPurchases) {
            $tabs = array_diff($tabs, ['compras']);
        }
        $tabs[] = 'top';

        $tab = in_array($request->query('tab'), $tabs, true) ? $request->query('tab') : 'servicios';

        return view('reports.analysis', $this->common($period) + [
            'tab' => $tab,
            'tabs' => array_values($tabs),
            'metric' => $request->query('metric') === 'cantidad' ? 'count' : 'amount',
            'analysis' => $report->for($period->branchId, $withPurchases),
        ]);
    }

    /** Lo que necesitan todas las vistas: el periodo y la barra de filtros. */
    protected function common(ReportPeriod $period): array
    {
        return [
            'period' => $period,
            'branches' => Branch::orderBy('name')->get(),
        ];
    }
}
