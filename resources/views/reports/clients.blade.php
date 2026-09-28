@extends('layouts.app')

@section('title', 'Reporte de clientes - Barbora')

@section('page')
@include('reports.partials.header', [
    'title' => 'Clientes',
    'icon' => 'bi-people',
    'export' => 'clientes',
])

@include('reports.partials.filters')

<div class="row g-3 mb-4">
    @php
        $cards = [
            ['label' => 'Clientes atendidos', 'value' => $summary['active']],
            ['label' => 'Nuevos en el periodo', 'value' => $summary['new']],
            ['label' => 'Vinieron más de una vez', 'value' => $summary['returning_rate'] !== null ? $summary['returning'].' ('.$summary['returning_rate'].'%)' : $summary['returning']],
            ['label' => 'Facturado', 'value' => \App\Support\Money::format($summary['revenue'], $tenantCompany)],
        ];
    @endphp
    @foreach($cards as $card)
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-3">
                    <p class="text-muted small mb-1">{{ $card['label'] }}</p>
                    <p class="h4 fw-bold mb-0">{{ $card['value'] }}</p>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-0">
                <div class="p-3 border-bottom"><h6 class="fw-bold mb-0">Los que más gastaron</h6></div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">#</th>
                                <th>Cliente</th>
                                <th class="text-center">Visitas</th>
                                <th class="text-end">Promedio</th>
                                <th class="text-end pe-3">Gastado</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($top as $i => $row)
                                <tr>
                                    <td class="ps-3 text-muted">{{ $i + 1 }}</td>
                                    <td>
                                        <span class="fw-semibold">{{ $row['client'] }}</span>
                                        @if($row['phone'])
                                            <small class="text-muted d-block">{{ $row['phone'] }}</small>
                                        @endif
                                    </td>
                                    <td class="text-center">{{ $row['visits'] }}</td>
                                    <td class="text-end text-muted">{{ \App\Support\Money::format($row['average'], $tenantCompany) }}</td>
                                    <td class="text-end pe-3 fw-semibold">{{ \App\Support\Money::format($row['spent'], $tenantCompany) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center py-5 text-muted">Ningún cliente compró en el periodo.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-0">
                <div class="p-3 border-bottom">
                    <h6 class="fw-bold mb-0">Hace tiempo que no vienen</h6>
                    <small class="text-muted">
                        Sin comprar desde hace más de {{ \App\Support\Reports\ClientsReport::LOST_AFTER_DAYS }} días.
                        Se mide contra hoy, no contra el periodo.
                    </small>
                </div>
                <div class="table-responsive" style="max-height: 420px; overflow-y: auto;">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Cliente</th>
                                <th class="text-end pe-3">Última visita</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($lost as $row)
                                <tr>
                                    <td class="ps-3">
                                        <span class="fw-semibold">{{ $row['client'] }}</span>
                                        @if($row['phone'])
                                            <small class="text-muted d-block">{{ $row['phone'] }}</small>
                                        @endif
                                    </td>
                                    <td class="text-end pe-3">
                                        {{ \Carbon\Carbon::parse($row['last_visit'])->translatedFormat('d/m/Y') }}
                                        <small class="text-muted d-block">
                                            {{ $row['visits'] }} {{ $row['visits'] === 1 ? 'visita' : 'visitas' }}
                                        </small>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="2" class="text-center py-4 text-muted">Ningún cliente se ha enfriado.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
