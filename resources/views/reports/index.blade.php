@extends('layouts.app')

@section('title', 'Reportes - Barbora')

@php
    $reports = [
        ['route' => 'reports.sales',    'icon' => 'bi-graph-up-arrow', 'color' => '#0ea5e9', 'title' => 'Ventas',    'text' => 'Cuánto se facturó, por día, sucursal y método de pago.'],
        ['route' => 'reports.services', 'icon' => 'bi-scissors',       'color' => '#16a34a', 'title' => 'Servicios',  'text' => 'Qué servicios se piden más y cómo va la asistencia a las citas.'],
        ['route' => 'reports.staff',    'icon' => 'bi-person-badge',   'color' => '#f59e0b', 'title' => 'Barberos',   'text' => 'Qué produce cada barbero, sus comisiones y sus propinas.'],
        ['route' => 'reports.products', 'icon' => 'bi-box-seam',       'color' => '#7c3aed', 'title' => 'Productos',  'text' => 'Rotación, margen estimado, stock bajo y producto parado.'],
        ['route' => 'reports.clients',  'icon' => 'bi-people',         'color' => '#e11d48', 'title' => 'Clientes',   'text' => 'Nuevos, recurrentes, los que más gastan y los perdidos.'],
        ['route' => 'reports.earnings', 'icon' => 'bi-cash-coin',      'color' => '#0f766e', 'title' => 'Ganancias',  'text' => 'De lo cobrado a lo que queda, descontando costo y comisiones.'],
    ];
@endphp

@section('page')
@include('reports.partials.header', ['title' => 'Reportes', 'icon' => 'bi-bar-chart'])

@include('reports.partials.filters')

<div class="row g-3 mb-4">
    @php
        $cards = [
            ['label' => 'Ventas',        'value' => $summary['sales'],                                        'raw' => true],
            ['label' => 'Facturado',     'value' => \App\Support\Money::format($summary['total'], $tenantCompany)],
            ['label' => 'Ticket medio',  'value' => \App\Support\Money::format($summary['ticket'], $tenantCompany)],
            ['label' => 'Propinas',      'value' => \App\Support\Money::format($summary['tip'], $tenantCompany)],
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

<div class="row g-3">
    @foreach($reports as $report)
        <div class="col-md-6 col-xl-4">
            <a href="{{ route($report['route'], $period->queryParams()) }}"
               class="card border-0 shadow-sm h-100 text-decoration-none text-body report-card">
                <div class="card-body d-flex gap-3">
                    <span class="report-card-icon" style="background: {{ $report['color'] }}1a; color: {{ $report['color'] }};">
                        <i class="bi {{ $report['icon'] }}"></i>
                    </span>
                    <div>
                        <h6 class="fw-bold mb-1">{{ $report['title'] }}</h6>
                        <p class="text-muted small mb-0">{{ $report['text'] }}</p>
                    </div>
                </div>
            </a>
        </div>
    @endforeach
</div>
@endsection
