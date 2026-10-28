<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Models\CashSession;
use App\Models\Company;
use App\Models\Product;
use App\Models\Sale;
use App\Support\ShopTime;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * El Inicio de la app: el resumen del día para el dueño o el encargado.
 *
 * Se entra con `dashboard.view`, pero cada bloque exige además su propio
 * módulo del plan y su permiso: quien no puede ver ventas no ve lo cobrado
 * aunque tenga el Inicio. Un bloque no permitido llega como null.
 *
 * «Hoy» es el día de la barbería, no el del servidor: ver ShopTime::now().
 */
class DashboardController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        /** @var Company $company */
        $company = $request->attributes->get('tenant_company');
        $now = ShopTime::now($company);
        $today = $now->copy()->startOfDay();

        $allows = fn (string $module, string $permission) => $company->planAllows($module)
            && $request->user()->hasPermissionInCompany($permission, $company);

        return $this->json([
            'date' => $today->toDateString(),
            'agenda' => $allows('agenda', 'appointments.view') ? $this->agenda($today, $now, $company) : null,
            'sales' => $allows('pos', 'sales.view') ? $this->sales($today) : null,
            'cash' => $allows('caja', 'cash.view') ? $this->cash($company) : null,
            'stock' => $allows('inventario', 'products.view') ? $this->stock() : null,
        ]);
    }

    /** Citas de hoy: cuántas, cómo van y las próximas que quedan. */
    protected function agenda(Carbon $today, Carbon $now, Company $company): array
    {
        $appointments = Appointment::with(['client', 'personal', 'services', 'branch'])
            ->whereBetween('starts_at', [$today, $today->copy()->endOfDay()])
            ->orderBy('starts_at')
            ->get();

        $upcoming = $appointments
            ->reject->isReleased()
            ->whereIn('status', ['reservada', 'confirmada'])
            ->filter(fn (Appointment $a) => $a->starts_at->greaterThanOrEqualTo($now->copy()->subMinutes(15)))
            ->take(3)
            ->values();

        return [
            'total' => $appointments->count(),
            'pending' => $appointments->whereIn('status', ['reservada', 'confirmada'])->count(),
            'attended' => $appointments->where('status', 'atendida')->count(),
            'no_show' => $appointments->where('status', 'no_show')->count(),
            'cancelled' => $appointments->where('status', 'cancelada')->count(),
            'next' => AppointmentResource::collection($upcoming)->resolve(),
        ];
    }

    /** Lo cobrado hoy frente a ayer, y la semana día a día para el gráfico. */
    protected function sales(Carbon $today): array
    {
        $from = $today->copy()->subDays(6);

        $byDay = Sale::paid()
            ->whereBetween('sold_at', [$from, $today->copy()->endOfDay()])
            ->get(['sold_at', 'total', 'tip'])
            ->groupBy(fn (Sale $s) => $s->sold_at->toDateString());

        $sum = fn (Carbon $day) => (float) ($byDay->get($day->toDateString())?->sum('total') ?? 0);
        $todaySales = $byDay->get($today->toDateString()) ?? collect();
        $count = $todaySales->count();
        $total = $sum($today);

        $week = collect(range(6, 0))->map(fn (int $back) => $today->copy()->subDays($back));

        return [
            'today' => $total,
            'count' => $count,
            'tips' => (float) $todaySales->sum('tip'),
            'average' => $count ? round($total / $count, 2) : 0.0,
            'yesterday' => $sum($today->copy()->subDay()),
            'week' => [
                'labels' => $week->map(fn (Carbon $d) => ucfirst($d->locale('es')->isoFormat('dd')))->all(),
                'data' => $week->map(fn (Carbon $d) => $sum($d))->all(),
            ],
        ];
    }

    /** ¿Hay caja abierta? Y cuánto efectivo debería haber en el cajón. */
    protected function cash(Company $company): ?array
    {
        $session = CashSession::open()->with(['caja', 'movements', 'openedBy'])->orderBy('opened_at')->first();

        if (! $session) {
            return ['open' => false];
        }

        return [
            'open' => true,
            'caja' => $session->caja?->name,
            'opened_by' => $session->openedBy?->name,
            'opened_at' => ShopTime::iso($session->opened_at, $company),
            'expected_cash' => $session->totals()['expected_cash'],
            'income' => $session->totals()['income'],
        ];
    }

    /** Lo que hay que reponer. */
    protected function stock(): array
    {
        $low = Product::tracked()->where('active', true)->with('stocks')->orderBy('name')->get()
            ->filter->isLowStock()->values();

        return [
            'low_count' => $low->count(),
            'items' => $low->take(5)->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'stock_label' => $p->formatQuantity($p->totalStock()),
            ])->all(),
        ];
    }
}
