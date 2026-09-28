@extends('layouts.app')

@section('title', 'Rendimiento por barbero - Barbora')

@section('page')
@include('reports.partials.header', [
    'title' => 'Barberos',
    'icon' => 'bi-person-badge',
    'export' => 'barberos',
    'subtitle' => 'Lo facturado se atribuye línea a línea; la propina, a quien atendió la venta.',
])

@include('reports.partials.filters')

<div class="row g-3 mb-4">
    @php
        $cards = [
            ['label' => 'Facturado', 'value' => \App\Support\Money::format($totals['revenue'], $tenantCompany)],
            ['label' => 'Comisiones', 'value' => \App\Support\Money::format($totals['commissions'], $tenantCompany)],
            ['label' => 'Propinas', 'value' => \App\Support\Money::format($totals['tips'], $tenantCompany)],
            ['label' => 'Citas atendidas', 'value' => $totals['attended']],
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

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Barbero</th>
                        <th class="text-center">Servicios</th>
                        <th class="text-center">Productos</th>
                        <th class="text-end">Facturado</th>
                        <th class="text-end">Ticket medio</th>
                        <th class="text-end">Comisiones</th>
                        <th class="text-end">Propinas</th>
                        <th class="text-center">Citas</th>
                        <th class="text-center pe-3">No show</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        @php $person = $row['personal']; @endphp
                        <tr>
                            <td class="ps-3">
                                <div class="d-flex align-items-center gap-2">
                                    @if($person->photoUrl())
                                        <img src="{{ $person->photoUrl() }}" alt="" class="staff-avatar">
                                    @else
                                        <span class="staff-avatar staff-avatar-initials"
                                              style="background: {{ $person->agenda_color }};">{{ $person->initials() }}</span>
                                    @endif
                                    <a href="{{ route('commissions.show', $person) }}" class="fw-semibold text-decoration-none">
                                        {{ $person->full_name }}
                                    </a>
                                </div>
                            </td>
                            <td class="text-center">{{ $row['services'] }}</td>
                            <td class="text-center">{{ $row['products'] }}</td>
                            <td class="text-end fw-semibold">{{ \App\Support\Money::format($row['revenue'], $tenantCompany) }}</td>
                            <td class="text-end text-muted">{{ \App\Support\Money::format($row['average'], $tenantCompany) }}</td>
                            <td class="text-end">{{ \App\Support\Money::format($row['commissions'], $tenantCompany) }}</td>
                            <td class="text-end text-muted">{{ \App\Support\Money::format($row['tips'], $tenantCompany) }}</td>
                            <td class="text-center">{{ $row['attended'] }} / {{ $row['appointments'] }}</td>
                            <td class="text-center pe-3">
                                @if($row['no_show'] > 0)
                                    <span class="badge bg-warning">{{ $row['no_show_rate'] }}%</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center py-5 text-muted">Ningún barbero registró actividad en el periodo.</td></tr>
                    @endforelse
                </tbody>
                @if($rows->isNotEmpty())
                    <tfoot class="table-light fw-bold">
                        <tr>
                            <td colspan="3" class="ps-3 text-end">Total</td>
                            <td class="text-end">{{ \App\Support\Money::format($totals['revenue'], $tenantCompany) }}</td>
                            <td></td>
                            <td class="text-end">{{ \App\Support\Money::format($totals['commissions'], $tenantCompany) }}</td>
                            <td class="text-end">{{ \App\Support\Money::format($totals['tips'], $tenantCompany) }}</td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>
@endsection
