@extends('layouts.app')

@section('title', 'Análisis - Barbora')

{{--
    Análisis (AnalysisReport): sin elegir fechas, cómo va el negocio contra
    antes. Servicios / Ventas / Compras en monto o cantidad, y el Top del mes.
    Barras en CSS puro, como el resto de los reportes.
--}}
@php
    $isAmount = $metric === 'amount';
    $fmt = fn ($v) => $isAmount
        ? \App\Support\Money::format($v, $tenantCompany)
        : rtrim(rtrim(number_format((float) $v, 2, '.', ','), '0'), '.');
    $url = fn (array $q) => request()->fullUrlWithQuery($q);
    $labels = \App\Support\Reports\AnalysisReport::SERIES + ['top' => 'Top'];
    $icons = ['servicios' => 'bi-scissors', 'ventas' => 'bi-cart', 'compras' => 'bi-bag', 'top' => 'bi-trophy'];
    $countLabel = $tab === 'compras' ? 'Cantidad (compras)' : 'Cantidad';
@endphp

@section('page')
<style>
    .an-chart { display: flex; align-items: flex-end; gap: .5rem; height: 200px; padding-top: .5rem; }
    .an-col { flex: 1; display: flex; flex-direction: column; align-items: center; height: 100%; min-width: 0; }
    .an-bars { flex: 1; width: 100%; display: flex; align-items: flex-end; justify-content: center; gap: 3px; }
    .an-bar { width: 100%; max-width: 34px; border-radius: 4px 4px 0 0; background: var(--bs-success); }
    .an-bar.prev { background: rgba(var(--bs-success-rgb), .3); }
    .an-label { font-size: .72rem; color: var(--bs-secondary-color); margin-top: .35rem; white-space: nowrap; }
</style>
@include('reports.partials.header', ['title' => 'Análisis', 'icon' => 'bi-activity', 'noPeriod' => true, 'subtitle' => 'Al día de hoy, comparado con el periodo anterior.'])

<div class="d-flex flex-wrap gap-3 align-items-center justify-content-between mb-3 d-print-none">
    <ul class="nav nav-pills">
        @foreach($tabs as $key)
            <li class="nav-item">
                <a href="{{ $url(['tab' => $key]) }}" class="nav-link {{ $tab === $key ? 'active' : '' }}">
                    <i class="bi {{ $icons[$key] }}"></i> {{ $labels[$key] }}
                </a>
            </li>
        @endforeach
    </ul>

    <div class="d-flex gap-2 align-items-center">
        @if($branches->count() > 1)
            <form method="GET" class="d-flex gap-2">
                <input type="hidden" name="tab" value="{{ $tab }}">
                <input type="hidden" name="metric" value="{{ $isAmount ? 'monto' : 'cantidad' }}">
                <select name="branch_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">Todas las sucursales</option>
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}" {{ $period->branchId === $branch->id ? 'selected' : '' }}>{{ $branch->name }}</option>
                    @endforeach
                </select>
            </form>
        @endif

        <div class="btn-group btn-group-sm">
            <a href="{{ $url(['metric' => 'monto']) }}" class="btn {{ $isAmount ? 'btn-primary' : 'btn-outline-primary' }}">Monto ({{ \App\Support\Money::symbol($tenantCompany) }})</a>
            <a href="{{ $url(['metric' => 'cantidad']) }}" class="btn {{ ! $isAmount ? 'btn-primary' : 'btn-outline-primary' }}">{{ $countLabel }}</a>
        </div>
    </div>
</div>

@if($tab === 'top')
    @php
        $key = $isAmount ? 'amount' : 'count';
        $blocks = ['services' => ['Servicios', 'bi-scissors'], 'products' => ['Productos', 'bi-box-seam'], 'clients' => ['Clientes', 'bi-people']];
    @endphp
    <p class="text-muted small">Lo más fuerte de este mes{{ $isAmount ? ', por monto' : ', por cantidad' }}.</p>
    <div class="row g-4">
        @foreach($blocks as $block => [$title, $icon])
            @php
                $rows = collect($analysis['top'][$block])->sortByDesc($key)->take(10)->values();
                $max = max(1, (float) $rows->max($key));
            @endphp
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <h6 class="fw-bold mb-3"><i class="bi {{ $icon }}"></i> {{ $title }}</h6>
                        @forelse($rows as $i => $row)
                            <div class="mb-2">
                                <div class="d-flex justify-content-between small">
                                    <span class="text-truncate me-2"><span class="text-muted">{{ $i + 1 }}.</span> {{ $row['name'] }}</span>
                                    <span class="fw-semibold text-nowrap">{{ $fmt($row[$key]) }}</span>
                                </div>
                                <div class="progress" style="height: 5px">
                                    <div class="progress-bar bg-success" style="width: {{ round($row[$key] / $max * 100, 1) }}%"></div>
                                </div>
                            </div>
                        @empty
                            <p class="text-muted small mb-0">Sin datos este mes.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@else
    @php
        $s = $analysis['series'][$tab][$metric];
        $cards = [['Semana', $s['week'], $s['week_change']], ['Mes', $s['month'], $s['month_change']]];
    @endphp

    <div class="row g-3 mb-4">
        @foreach($cards as [$label, $value, $change])
            <div class="col-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body py-3">
                        <p class="text-muted small mb-1">{{ $label }}</p>
                        <p class="h4 fw-bold text-success mb-1">{{ $fmt($value) }}</p>
                        @if($change === null)
                            <span class="small text-muted">Sin datos del mismo tramo anterior</span>
                        @else
                            <span class="small {{ $change >= 0 ? 'text-success' : 'text-danger' }}">
                                <i class="bi {{ $change >= 0 ? 'bi-graph-up-arrow' : 'bi-graph-down-arrow' }}"></i>
                                {{ $change > 0 ? '+' : '' }}{{ number_format($change, 1) }}% vs mismo tramo anterior
                            </span>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between flex-wrap gap-2 mb-2">
                        <h6 class="fw-bold mb-0">Semana anterior vs. actual (por día)</h6>
                        <span class="small text-muted">
                            <span class="an-bar prev d-inline-block align-middle" style="width: 12px; height: 12px"></span> Semana anterior
                            <span class="an-bar d-inline-block align-middle ms-3" style="width: 12px; height: 12px"></span> Semana actual
                        </span>
                    </div>
                    @php $max = max(1, collect($s['daily'])->flatMap(fn ($d) => [$d['current'], $d['previous']])->max()); @endphp
                    <div class="an-chart">
                        @foreach($s['daily'] as $d)
                            <div class="an-col" title="{{ $d['label'] }} · anterior {{ $fmt($d['previous']) }} · actual {{ $fmt($d['current']) }}">
                                <div class="an-bars">
                                    <div class="an-bar prev" style="height: {{ round($d['previous'] / $max * 100, 2) }}%"></div>
                                    <div class="an-bar" style="height: {{ round($d['current'] / $max * 100, 2) }}%"></div>
                                </div>
                                <span class="an-label">{{ $d['label'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        @foreach(['weeks' => 'Comparativa semanal (últimas 8)', 'months' => 'Comparativa mensual (últimos 6)'] as $series => $title)
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <h6 class="fw-bold mb-2">{{ $title }}</h6>
                        @php $max = max(1, collect($s[$series])->max('value')); @endphp
                        <div class="an-chart">
                            @foreach($s[$series] as $p)
                                <div class="an-col" title="{{ $p['label'] }} · {{ $fmt($p['value']) }}">
                                    <div class="an-bars">
                                        <div class="an-bar" style="height: {{ round($p['value'] / $max * 100, 2) }}%"></div>
                                    </div>
                                    <span class="an-label">{{ $p['label'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <p class="small text-muted mt-3">
        La semana y el mes se comparan con el mismo tramo del anterior (si hoy es miércoles, de lunes a miércoles).
        @if($tab === 'compras') En compras, la cantidad es el número de compras. @else La cantidad son las unidades vendidas. @endif
    </p>
@endif
@endsection
