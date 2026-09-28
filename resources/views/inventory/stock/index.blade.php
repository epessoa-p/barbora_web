@extends('layouts.app')

@section('title', 'Existencias - Barbora')

@section('page')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h4 fw-bold mb-1"><i class="bi bi-boxes"></i> Existencias</h1>
        <p class="text-muted mb-0">Qué hay y dónde está.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('stock.movements') }}" class="btn btn-light border">
            <i class="bi bi-clock-history"></i> Movimientos
        </a>
        <a href="{{ route('stock.create', ['type' => 'entrada']) }}" class="btn btn-success">
            <i class="bi bi-arrow-down-circle"></i> Entrada
        </a>
        <a href="{{ route('stock.create', ['type' => 'salida']) }}" class="btn btn-danger">
            <i class="bi bi-arrow-up-circle"></i> Salida
        </a>
    </div>
</div>

@if($lowCount > 0 && request('filter') !== 'low')
    <div class="alert alert-warning d-flex justify-content-between align-items-center">
        <span>
            <i class="bi bi-exclamation-triangle-fill"></i>
            Hay <strong>{{ $lowCount }}</strong> {{ $lowCount === 1 ? 'producto' : 'productos' }} por debajo del stock mínimo.
        </span>
        <a href="{{ route('stock.index', ['filter' => 'low']) }}" class="btn btn-sm btn-outline-dark">Ver cuáles</a>
    </div>
@endif

<form method="GET" class="row g-2 mb-3">
    <div class="col-md-4">
        <input type="search" name="q" class="form-control" placeholder="Buscar producto…" value="{{ request('q') }}">
    </div>
    <div class="col-md-3">
        <select name="warehouse" class="form-select">
            <option value="">Todos los almacenes</option>
            @foreach($warehouses as $warehouse)
                <option value="{{ $warehouse->id }}" {{ (string) request('warehouse') === (string) $warehouse->id ? 'selected' : '' }}>
                    {{ $warehouse->name }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3">
        <select name="filter" class="form-select">
            <option value="">Todas las existencias</option>
            <option value="low" {{ request('filter') === 'low' ? 'selected' : '' }}>Solo stock bajo</option>
        </select>
    </div>
    <div class="col-md-2 d-flex gap-2">
        <button class="btn btn-outline-secondary flex-fill"><i class="bi bi-funnel"></i></button>
        @if(request()->hasAny(['q', 'warehouse', 'filter']))
            <a href="{{ route('stock.index') }}" class="btn btn-light border">Limpiar</a>
        @endif
    </div>
</form>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Producto</th>
                        <th>Categoría</th>
                        <th>Almacén</th>
                        <th class="text-end">Existencias</th>
                        <th class="text-end">Mínimo</th>
                        <th class="text-end pe-3">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($stocks as $stock)
                        <tr class="{{ $stock->isLow() ? 'table-warning' : '' }}">
                            <td class="ps-3">
                                <a href="{{ route('products.show', $stock->product) }}" class="fw-semibold text-decoration-none">
                                    {{ $stock->product?->name }}
                                </a>
                                @if($stock->product?->sku)
                                    <small class="text-muted d-block">{{ $stock->product->sku }}</small>
                                @endif
                            </td>
                            <td>{{ $stock->product?->category?->name ?? '—' }}</td>
                            <td>{{ $stock->warehouse?->name }}</td>
                            <td class="text-end fw-semibold {{ $stock->isLow() ? 'text-danger' : '' }}">
                                {{ $stock->product?->formatQuantity((float) $stock->quantity) }}
                                @if($stock->isLow())
                                    <i class="bi bi-exclamation-triangle-fill text-warning"></i>
                                @endif
                            </td>
                            <td class="text-end text-muted">
                                {{ rtrim(rtrim(number_format((float) ($stock->product?->min_stock ?? 0), 2), '0'), '.') }}
                            </td>
                            <td class="text-end pe-3">
                                <a href="{{ route('stock.create', ['product' => $stock->product_id, 'type' => 'entrada']) }}"
                                   class="btn btn-sm btn-outline-success" title="Entrada"><i class="bi bi-arrow-down"></i></a>
                                <a href="{{ route('stock.create', ['product' => $stock->product_id, 'type' => 'salida']) }}"
                                   class="btn btn-sm btn-outline-danger" title="Salida"><i class="bi bi-arrow-up"></i></a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                @if(request('filter') === 'low')
                                    Ningún producto está por debajo del mínimo.
                                @else
                                    Sin existencias registradas. Empieza con una entrada.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
