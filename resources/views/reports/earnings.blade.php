@extends('layouts.app')

@section('title', 'Reporte de ganancias - Barbora')

@section('page')
@include('reports.partials.header', [
    'title' => 'Ganancias',
    'icon' => 'bi-cash-coin',
    'export' => 'ganancias',
])

@include('reports.partials.filters')

<div class="alert alert-info py-2 small d-flex gap-2 align-items-start d-print-none">
    <i class="bi bi-info-circle mt-1"></i>
    <span>
        Es una cifra de gestión, no contabilidad: no se descuentan alquiler, sueldos
        fijos ni servicios.
        @if($earnings['estimated'])
            Además, algunas ventas son anteriores a que se guardara el precio de compra,
            así que su costo se estima con el actual.
        @endif
    </span>
</div>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="fw-bold mb-3">De lo cobrado a lo que queda</h6>

                @php
                    $lines = [
                        ['label' => 'Ingreso por ventas', 'value' => $earnings['revenue'], 'sign' => null, 'strong' => false],
                        ['label' => 'Costo de productos', 'value' => -$earnings['product_cost'], 'sign' => 'neg', 'strong' => false],
                    ];
                @endphp

                @foreach($lines as $line)
                    <div class="d-flex justify-content-between py-1 border-bottom">
                        <span class="text-muted">{{ $line['label'] }}</span>
                        <span class="{{ $line['sign'] === 'neg' ? 'text-danger' : '' }}">
                            {{ \App\Support\Money::format($line['value'], $tenantCompany) }}
                        </span>
                    </div>
                @endforeach

                <div class="d-flex justify-content-between py-2 fw-bold border-bottom">
                    <span>Margen bruto</span>
                    <span>{{ \App\Support\Money::format($earnings['gross'], $tenantCompany) }}</span>
                </div>

                <div class="d-flex justify-content-between py-1 border-bottom">
                    <span class="text-muted">Comisiones devengadas</span>
                    <span class="text-danger">{{ \App\Support\Money::format(-$earnings['commissions'], $tenantCompany) }}</span>
                </div>
                <div class="d-flex justify-content-between py-1 border-bottom">
                    <span class="text-muted">Otros egresos de caja</span>
                    <span class="text-danger">{{ \App\Support\Money::format(-$earnings['other_expenses'], $tenantCompany) }}</span>
                </div>

                <div class="d-flex justify-content-between py-3 fs-5 fw-bold">
                    <span>Resultado</span>
                    <span class="{{ $earnings['net'] >= 0 ? 'text-success' : 'text-danger' }}">
                        {{ \App\Support\Money::format($earnings['net'], $tenantCompany) }}
                    </span>
                </div>

                @if($earnings['margin_pct'] !== null)
                    <p class="text-muted small mb-0">
                        Margen del {{ $earnings['margin_pct'] }}% sobre lo facturado.
                    </p>
                @endif
            </div>
        </div>

        <div class="card border-0 shadow-sm mt-4">
            <div class="card-body">
                <h6 class="fw-bold mb-3">Fuera del resultado</h6>
                <div class="d-flex justify-content-between py-1 border-bottom">
                    <span class="text-muted">Propinas</span>
                    <span>{{ \App\Support\Money::format($earnings['tips'], $tenantCompany) }}</span>
                </div>
                <div class="d-flex justify-content-between py-1">
                    <span class="text-muted">Descuentos aplicados</span>
                    <span>{{ \App\Support\Money::format($earnings['discount'], $tenantCompany) }}</span>
                </div>
                <p class="text-muted small mb-0 mt-2">
                    La propina es del barbero, no de la barbería: entra y sale, por eso no suma al resultado.
                </p>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="row g-3 mb-4">
            @php
                $cards = [
                    ['label' => 'Ventas', 'value' => $earnings['sales']],
                    ['label' => 'Ticket medio', 'value' => \App\Support\Money::format($earnings['ticket'], $tenantCompany)],
                    ['label' => 'Margen bruto', 'value' => \App\Support\Money::format($earnings['gross'], $tenantCompany)],
                ];
            @endphp
            @foreach($cards as $card)
                <div class="col-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body py-3">
                            <p class="text-muted small mb-1">{{ $card['label'] }}</p>
                            <p class="h5 fw-bold mb-0">{{ $card['value'] }}</p>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="fw-bold mb-3">Facturación por día</h6>
                @include('reports.partials.bar-chart', ['series' => $earnings['daily'], 'company' => $tenantCompany])
            </div>
        </div>
    </div>
</div>
@endsection
