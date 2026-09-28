@extends('layouts.app')

@section('title', 'Reporte de servicios - Barbora')

@section('page')
@include('reports.partials.header', [
    'title' => 'Servicios',
    'icon' => 'bi-scissors',
    'export' => 'servicios',
])

@include('reports.partials.filters')

<div class="row g-3 mb-4">
    @php
        $cards = [
            ['label' => 'Servicios vendidos', 'value' => (int) $totals['quantity']],
            ['label' => 'Ingreso', 'value' => \App\Support\Money::format($totals['revenue'], $tenantCompany)],
            ['label' => 'Citas atendidas', 'value' => $appointments['attended'].' de '.$appointments['total']],
            ['label' => 'No se presentaron', 'value' => $appointments['no_show_rate'] !== null ? $appointments['no_show_rate'].'%' : '—'],
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

@if($appointments['no_show'] > 0)
    <div class="alert alert-warning py-2 small d-flex gap-2 align-items-center">
        <i class="bi bi-person-x"></i>
        <span>
            {{ $appointments['no_show'] }} {{ $appointments['no_show'] === 1 ? 'cliente no se presentó' : 'clientes no se presentaron' }}
            a su cita. Son huecos de agenda que nadie ocupó.
        </span>
    </div>
@endif

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="p-3 border-bottom">
            <h6 class="fw-bold mb-0">Ranking por ingreso</h6>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">#</th>
                        <th>Servicio</th>
                        <th class="text-center">Veces</th>
                        <th class="text-center">Cantidad</th>
                        <th class="text-end">Precio medio</th>
                        <th class="text-end pe-3">Ingreso</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $i => $row)
                        <tr>
                            <td class="ps-3 text-muted">{{ $i + 1 }}</td>
                            <td class="fw-semibold">
                                {{ $row['service'] }}
                                @unless($row['active'])
                                    <span class="badge bg-secondary ms-1">inactivo</span>
                                @endunless
                            </td>
                            <td class="text-center">{{ $row['times'] }}</td>
                            <td class="text-center">{{ rtrim(rtrim(number_format($row['quantity'], 2, '.', ''), '0'), '.') }}</td>
                            <td class="text-end text-muted">{{ \App\Support\Money::format($row['average'], $tenantCompany) }}</td>
                            <td class="text-end pe-3 fw-semibold">{{ \App\Support\Money::format($row['revenue'], $tenantCompany) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center py-5 text-muted">No se vendió ningún servicio en el periodo.</td></tr>
                    @endforelse
                </tbody>
                @if($rows->isNotEmpty())
                    <tfoot class="table-light fw-bold">
                        <tr>
                            <td colspan="5" class="ps-3 text-end">Total</td>
                            <td class="text-end pe-3">{{ \App\Support\Money::format($totals['revenue'], $tenantCompany) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>
@endsection
