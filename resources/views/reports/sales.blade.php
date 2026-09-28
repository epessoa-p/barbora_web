@extends('layouts.app')

@section('title', 'Reporte de ventas - Barbora')

@section('page')
@include('reports.partials.header', [
    'title' => 'Ventas',
    'icon' => 'bi-graph-up-arrow',
    'export' => 'ventas',
])

@include('reports.partials.filters')

<div class="row g-3 mb-4">
    @php
        $cards = [
            ['label' => 'Facturado', 'value' => \App\Support\Money::format($totals['total'], $tenantCompany), 'change' => $change['total']],
            ['label' => 'Ventas',    'value' => $totals['sales'],                                             'change' => $change['sales']],
            ['label' => 'Ticket medio', 'value' => \App\Support\Money::format($totals['ticket'], $tenantCompany), 'change' => $change['ticket']],
            ['label' => 'Propinas',  'value' => \App\Support\Money::format($totals['tip'], $tenantCompany),   'change' => null],
        ];
    @endphp

    @foreach($cards as $card)
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-3">
                    <p class="text-muted small mb-1">{{ $card['label'] }}</p>
                    <p class="h4 fw-bold mb-1">{{ $card['value'] }}</p>
                    @if($card['change'] !== null)
                        <span class="badge {{ $card['change'] >= 0 ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">
                            <i class="bi {{ $card['change'] >= 0 ? 'bi-arrow-up' : 'bi-arrow-down' }}"></i>
                            {{ abs($card['change']) }}%
                        </span>
                        <small class="text-muted d-block mt-1">vs. periodo anterior</small>
                    @else
                        <small class="text-muted">&nbsp;</small>
                    @endif
                </div>
            </div>
        </div>
    @endforeach
</div>

@if($totals['cancelled'] > 0)
    <div class="alert alert-warning py-2 small d-flex gap-2 align-items-center">
        <i class="bi bi-exclamation-triangle"></i>
        <span>
            {{ $totals['cancelled'] }} {{ $totals['cancelled'] === 1 ? 'venta anulada' : 'ventas anuladas' }}
            por {{ \App\Support\Money::format($totals['cancelled_total'], $tenantCompany) }}.
            No cuentan en las cifras de arriba.
        </span>
    </div>
@endif

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <h6 class="fw-bold mb-3">Ventas por día</h6>
        @include('reports.partials.bar-chart', ['series' => $daily, 'company' => $tenantCompany])
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-0">
                <div class="p-3 border-bottom"><h6 class="fw-bold mb-0">Por método de pago</h6></div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Método</th>
                                <th class="text-center">Pagos</th>
                                <th class="text-end pe-3">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($byMethod as $row)
                                <tr>
                                    <td class="ps-3">{{ $row['method'] }}</td>
                                    <td class="text-center">{{ $row['payments'] }}</td>
                                    <td class="text-end pe-3 fw-semibold">
                                        {{ \App\Support\Money::format($row['total'], $tenantCompany) }}
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center py-4 text-muted">Sin cobros en el periodo.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-0">
                <div class="p-3 border-bottom"><h6 class="fw-bold mb-0">Por sucursal</h6></div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Sucursal</th>
                                <th class="text-center">Ventas</th>
                                <th class="text-end pe-3">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($byBranch as $row)
                                <tr>
                                    <td class="ps-3">{{ $row['branch'] }}</td>
                                    <td class="text-center">{{ $row['sales'] }}</td>
                                    <td class="text-end pe-3 fw-semibold">
                                        {{ \App\Support\Money::format($row['total'], $tenantCompany) }}
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center py-4 text-muted">Sin ventas en el periodo.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
