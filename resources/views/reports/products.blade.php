@extends('layouts.app')

@section('title', 'Reporte de productos - Barbora')

@section('page')
@include('reports.partials.header', [
    'title' => 'Productos',
    'icon' => 'bi-box-seam',
    'export' => 'productos',
    // El aviso solo aparece si de verdad hubo que estimar algo: cuando las
    // ventas viejas salgan del periodo, desaparece solo.
    'subtitle' => $estimated
        ? 'Algunas ventas son anteriores a que se guardara el precio de compra: su costo se estima con el actual.'
        : 'El costo es el que tenía el producto el día de cada venta.',
])

@include('reports.partials.filters')

<div class="row g-3 mb-4">
    @php
        $cards = [
            ['label' => 'Unidades vendidas', 'value' => rtrim(rtrim(number_format($totals['quantity'], 2, '.', ''), '0'), '.')],
            ['label' => 'Ingreso', 'value' => \App\Support\Money::format($totals['revenue'], $tenantCompany)],
            ['label' => $estimated ? 'Costo (con estimación)' : 'Costo', 'value' => \App\Support\Money::format($totals['cost'], $tenantCompany)],
            ['label' => $estimated ? 'Margen (con estimación)' : 'Margen', 'value' => \App\Support\Money::format($totals['margin'], $tenantCompany)],
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

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-0">
        <div class="p-3 border-bottom"><h6 class="fw-bold mb-0">Lo que más rota</h6></div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">#</th>
                        <th>Producto</th>
                        <th class="text-center">Cantidad</th>
                        <th class="text-end">Ingreso</th>
                        <th class="text-end">Costo</th>
                        <th class="text-end">Margen</th>
                        <th class="text-end pe-3">%</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $i => $row)
                        <tr>
                            <td class="ps-3 text-muted">{{ $i + 1 }}</td>
                            <td class="fw-semibold">{{ $row['product'] }}</td>
                            <td class="text-center">
                                {{ rtrim(rtrim(number_format($row['quantity'], 2, '.', ''), '0'), '.') }}
                                <small class="text-muted">{{ $row['unit'] }}</small>
                            </td>
                            <td class="text-end">{{ \App\Support\Money::format($row['revenue'], $tenantCompany) }}</td>
                            <td class="text-end text-muted">
                                @if($row['has_cost'])
                                    {{ \App\Support\Money::format($row['cost'], $tenantCompany) }}
                                    @if($row['estimated'])
                                        <i class="bi bi-asterisk text-warning" style="font-size:.6rem"
                                           title="Parte de estas ventas no tenía el costo guardado y se estimó con el actual"></i>
                                    @endif
                                @else
                                    <span title="El producto no tiene precio de compra cargado">—</span>
                                @endif
                            </td>
                            <td class="text-end fw-semibold">{{ \App\Support\Money::format($row['margin'], $tenantCompany) }}</td>
                            <td class="text-end pe-3">
                                {{ $row['margin_pct'] !== null ? $row['margin_pct'].'%' : '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center py-5 text-muted">No se vendió ningún producto en el periodo.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-0">
                <div class="p-3 border-bottom">
                    <h6 class="fw-bold mb-0"><i class="bi bi-exclamation-triangle text-warning"></i> Stock bajo</h6>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Producto</th>
                                <th>Almacén</th>
                                <th class="text-end pe-3">Quedan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($lowStock as $row)
                                <tr>
                                    <td class="ps-3 fw-semibold">{{ $row['product'] }}</td>
                                    <td class="text-muted">{{ $row['warehouse'] }}</td>
                                    <td class="text-end pe-3">
                                        <span class="badge bg-warning">
                                            {{ rtrim(rtrim(number_format($row['quantity'], 2, '.', ''), '0'), '.') }}
                                        </span>
                                        <small class="text-muted d-block">mín. {{ rtrim(rtrim(number_format($row['min_stock'], 2, '.', ''), '0'), '.') }}</small>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center py-4 text-muted">Ningún producto por debajo del mínimo.</td></tr>
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
                <div class="p-3 border-bottom">
                    <h6 class="fw-bold mb-0">Sin salida en el periodo</h6>
                </div>
                <div class="table-responsive" style="max-height: 320px; overflow-y: auto;">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Producto</th>
                                <th class="text-end pe-3">Precio</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($idle as $row)
                                <tr>
                                    <td class="ps-3">{{ $row['product'] }}</td>
                                    <td class="text-end pe-3 text-muted">
                                        {{ \App\Support\Money::format($row['sale_price'], $tenantCompany) }}
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="2" class="text-center py-4 text-muted">Todo el catálogo tuvo salida.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
