@extends('layouts.app')

@section('title', 'Balance - Barbora')

{{--
    Balance del dinero (BalanceReport): lo que entró y salió de verdad.
      Resultados   ingresos y egresos por categoría, la utilidad y los aportes.
      Movimientos  caja / tesorería / todo, con totales, por categoría y la lista.
      Cierres      los turnos de caja del periodo y su arqueo.
--}}
@php
    $money = fn ($v) => \App\Support\Money::format($v, $tenantCompany);
    $r = $balance['results'];
    $flow = $balance['flow'];
    $url = fn (array $q) => request()->fullUrlWithQuery($q);
@endphp

@section('page')
@include('reports.partials.header', ['title' => 'Balance', 'icon' => 'bi-journal-text'])

@include('reports.partials.filters')

<ul class="nav nav-tabs mb-4 d-print-none">
    @foreach(['resultados' => ['Resultados', 'bi-clipboard-data'], 'movimientos' => ['Movimientos', 'bi-arrow-down-up'], 'cierres' => ['Cierres', 'bi-lock']] as $key => [$label, $icon])
        <li class="nav-item">
            <a href="{{ $url(['tab' => $key]) }}" class="nav-link {{ $tab === $key ? 'active fw-semibold' : '' }}">
                <i class="bi {{ $icon }}"></i> {{ $label }}
            </a>
        </li>
    @endforeach
</ul>

@if($tab === 'resultados')
    <div class="row g-4">
        <div class="col-lg-6">
            {{-- Ingresos --}}
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <h6 class="fw-bold text-success mb-3">Ingresos</h6>
                    @forelse($r['income']['lines'] as $line)
                        <div class="d-flex justify-content-between py-1">
                            <span>{{ $line['label'] }}</span>
                            <span>{{ $money($line['total']) }}</span>
                        </div>
                    @empty
                        <p class="text-muted small mb-0">Sin ingresos en el periodo.</p>
                    @endforelse
                    <hr>
                    <div class="d-flex justify-content-between fw-bold">
                        <span>Total ingresos</span>
                        <span class="text-success">{{ $money($r['income']['total']) }}</span>
                    </div>
                    <div class="small text-muted mt-1">
                        <i class="bi bi-cash-stack"></i> Caja {{ $money($r['income']['by_source']['caja']) }}
                        <span class="ms-3"><i class="bi bi-bank"></i> Tesorería {{ $money($r['income']['by_source']['tesoreria']) }}</span>
                    </div>
                </div>
            </div>

            {{-- Egresos --}}
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="fw-bold text-danger mb-3">Egresos</h6>
                    @forelse($r['expense']['lines'] as $line)
                        <div class="d-flex justify-content-between py-1">
                            <span>{{ $line['label'] }}</span>
                            <span>{{ $money($line['total']) }}</span>
                        </div>
                    @empty
                        <p class="text-muted small mb-0">Sin egresos en el periodo.</p>
                    @endforelse
                    <hr>
                    <div class="d-flex justify-content-between fw-bold">
                        <span>Total egresos</span>
                        <span class="text-danger">{{ $money($r['expense']['total']) }}</span>
                    </div>
                    <div class="small text-muted mt-1">
                        <i class="bi bi-cash-stack"></i> Caja {{ $money($r['expense']['by_source']['caja']) }}
                        <span class="ms-3"><i class="bi bi-bank"></i> Tesorería {{ $money($r['expense']['by_source']['tesoreria']) }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card border-0 shadow-sm mb-4" style="background: {{ $r['profit'] >= 0 ? '#f0fdf4' : '#fef2f2' }}">
                <div class="card-body p-4 d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Resultado del periodo</div>
                        <div class="fw-bold">{{ $r['profit'] >= 0 ? 'Utilidad' : 'Pérdida' }}</div>
                    </div>
                    <div class="display-6 fw-bold {{ $r['profit'] >= 0 ? 'text-success' : 'text-danger' }}">{{ $money($r['profit']) }}</div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body d-flex gap-3 align-items-start">
                    <i class="bi bi-piggy-bank fs-3 text-primary"></i>
                    <div class="flex-grow-1">
                        <div class="d-flex justify-content-between">
                            <span class="fw-bold">Aportes de capital</span>
                            <span class="fw-bold text-primary">{{ $money($r['capital']['contributions']) }}</span>
                        </div>
                        @if($r['capital']['withdrawals'] > 0)
                            <div class="d-flex justify-content-between">
                                <span>Retiros de capital</span>
                                <span class="text-primary">{{ $money(-$r['capital']['withdrawals']) }}</span>
                            </div>
                        @endif
                        <p class="text-muted small mb-0">Dinero que puso (o sacó) el dueño en las cuentas. No es ganancia: no entra en la utilidad.</p>
                    </div>
                </div>
            </div>

            <div class="alert alert-info small py-2">
                <i class="bi bi-info-circle"></i>
                Cuenta el dinero, no lo vendido: una compra resta el día que se paga. Para la ganancia por lo vendido
                (precio menos costo) mira <a href="{{ route('reports.earnings', $period->queryParams()) }}">Ganancias</a>.
                Las transferencias entre cuentas no cuentan.
            </div>
        </div>
    </div>

@elseif($tab === 'movimientos')
    <div class="d-flex flex-wrap gap-3 align-items-center mb-3 d-print-none">
        <div class="btn-group btn-group-sm">
            @foreach(['caja' => ['Caja', 'bi-cash-stack'], 'tesoreria' => ['Tesorería', 'bi-bank'], 'todo' => ['Todo', 'bi-collection']] as $value => [$label, $icon])
                <a href="{{ $url(['source' => $value]) }}" class="btn {{ $balance['source'] === $value ? 'btn-primary' : 'btn-outline-primary' }}">
                    <i class="bi {{ $icon }}"></i> {{ $label }}
                </a>
            @endforeach
        </div>
        <div class="btn-group btn-group-sm">
            @foreach(['' => 'Todos', 'ingreso' => 'Ingresos', 'egreso' => 'Egresos'] as $value => $label)
                <a href="{{ $url(['type' => $value ?: null]) }}" class="btn {{ ($balance['type'] ?? '') === $value ? 'btn-secondary' : 'btn-outline-secondary' }}">{{ $label }}</a>
            @endforeach
        </div>
        <span class="small text-muted">
            @switch($balance['source'])
                @case('caja') Solo movimientos de caja: es lo que cuadra con los cierres. @break
                @case('tesoreria') Solo las cuentas (capital, banco…), sin transferencias entre ellas. @break
                @default Caja y tesorería juntas.
            @endswitch
        </span>
    </div>

    <div class="row g-3 mb-4">
        @foreach([['Ingresos', $flow['income'], 'text-success'], ['Egresos', $flow['expense'], 'text-danger'], ['Balance', $flow['balance'], '']] as [$label, $value, $class])
            <div class="col-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body py-3">
                        <p class="text-muted small text-uppercase mb-1">{{ $label }}</p>
                        <p class="h5 fw-bold mb-0 {{ $class }}">{{ $money($value) }}</p>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="fw-bold mb-3">Por categoría</h6>
                    @php $max = max(1, collect($flow['categories'])->max('total') ?? 1); @endphp
                    @forelse($flow['categories'] as $cat)
                        @php $in = $cat['type'] === 'ingreso'; @endphp
                        <div class="mb-3">
                            <div class="d-flex justify-content-between small">
                                <span>{{ $cat['label'] }}</span>
                                <span class="fw-semibold {{ $in ? 'text-success' : 'text-danger' }}">{{ $money($cat['total']) }}</span>
                            </div>
                            <div class="progress" style="height: 6px">
                                <div class="progress-bar {{ $in ? 'bg-success' : 'bg-danger' }}" style="width: {{ round($cat['total'] / $max * 100, 1) }}%"></div>
                            </div>
                        </div>
                    @empty
                        <p class="text-muted small mb-0">Sin movimientos.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="fw-bold mb-3">Movimientos ({{ $flow['count'] }})</h6>
                    @if($flow['count'] > count($flow['items']))
                        <p class="small text-muted">Se muestran los {{ count($flow['items']) }} más recientes; los totales cuentan todos.</p>
                    @endif
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <tbody>
                                @php $day = null; @endphp
                                @forelse($flow['items'] as $m)
                                    @php $at = \Carbon\Carbon::parse($m['at']); $in = $m['type'] === 'ingreso'; @endphp
                                    @if($day !== $at->toDateString())
                                        @php $day = $at->toDateString(); @endphp
                                        <tr class="table-light"><td colspan="3" class="small fw-semibold text-muted">{{ ucfirst($at->translatedFormat('l j \d\e F')) }}</td></tr>
                                    @endif
                                    <tr>
                                        <td style="width: 2rem">
                                            <i class="bi {{ $in ? 'bi-arrow-down-circle text-success' : 'bi-arrow-up-circle text-danger' }}"></i>
                                        </td>
                                        <td>
                                            <div>{{ $m['concept'] }}</div>
                                            <div class="small text-muted">
                                                {{ $m['category_label'] }} · {{ $at->format('H:i') }}
                                                @if($m['place']) · {{ $m['place'] }} @endif
                                                @if($m['method']) · {{ $m['method'] }} @endif
                                                @if($m['source'] === 'tesoreria') · <i class="bi bi-bank"></i> Tesorería @endif
                                            </div>
                                        </td>
                                        <td class="text-end fw-semibold text-nowrap {{ $in ? 'text-success' : 'text-danger' }}">
                                            {{ $in ? '+' : '−' }}{{ $money($m['amount']) }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td class="text-muted small">Sin movimientos en el periodo.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

@else
    <div class="row g-3">
        @forelse($balance['closings'] as $c)
            @php
                $open = $c['status'] === 'abierta';
                $diff = $c['difference'];
            @endphp
            <div class="col-md-6 col-xl-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-1">
                            <h6 class="fw-bold mb-0">{{ collect([$c['caja'], $c['branch']])->filter()->implode(' · ') }}</h6>
                            <span class="badge rounded-pill {{ $open ? 'bg-warning-subtle text-warning-emphasis' : 'bg-success-subtle text-success' }}">{{ $open ? 'Abierta' : 'Cerrada' }}</span>
                        </div>
                        <div class="small text-muted">
                            {{ $c['number'] }} · Abrió {{ $c['opened_by'] ?? '—' }} · {{ \Carbon\Carbon::parse($c['opened_at'])->format('d/m H:i') }}
                            @if($c['closed_at'])
                                <br>Cerró {{ $c['closed_by'] ?? '—' }} · {{ \Carbon\Carbon::parse($c['closed_at'])->format('d/m H:i') }}
                            @endif
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between small"><span class="text-muted">Apertura</span><span>{{ $money($c['opening']) }}</span></div>
                        <div class="d-flex justify-content-between small"><span class="text-muted">Ingresos efectivo</span><span class="text-success">+{{ $money($c['cash_income']) }}</span></div>
                        <div class="d-flex justify-content-between small"><span class="text-muted">Egresos efectivo</span><span class="text-danger">−{{ $money($c['cash_expense']) }}</span></div>
                        <div class="d-flex justify-content-between small fw-bold"><span class="text-muted fw-normal">Esperado en el cajón</span><span>{{ $money($c['expected']) }}</span></div>
                        @unless($open)
                            <div class="d-flex justify-content-between small fw-bold"><span class="text-muted fw-normal">Contado</span><span>{{ $money($c['counted']) }}</span></div>
                            <div class="d-flex justify-content-between small fw-bold">
                                <span class="text-muted fw-normal">Diferencia</span>
                                @if(abs($diff) < 0.01)
                                    <span class="text-success">Exacto</span>
                                @elseif($diff > 0)
                                    <span class="text-primary">Sobrante {{ $money($diff) }}</span>
                                @else
                                    <span class="text-danger">Faltante {{ $money(-$diff) }}</span>
                                @endif
                            </div>
                        @endunless
                        @if($c['other_methods'])
                            <div class="small text-primary mt-2">
                                Otros medios: {{ collect($c['other_methods'])->map(fn ($o) => $o['label'].' '.$money($o['amount']))->implode(' · ') }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12"><p class="text-muted">No hay turnos de caja en el periodo.</p></div>
        @endforelse
    </div>
@endif
@endsection
