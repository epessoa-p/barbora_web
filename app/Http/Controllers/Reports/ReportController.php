<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Support\ReportPeriod;
use App\Support\Reports\ClientsReport;
use App\Support\Reports\EarningsReport;
use App\Support\Reports\ProductsReport;
use App\Support\Reports\SalesReport;
use App\Support\Reports\ServicesReport;
use App\Support\Reports\StaffReport;
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

        return view('reports.earnings', $this->common($period) + ['earnings' => $report->for($period)]);
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
