@extends('layouts.app')

@section('title', 'Movimientos de stock - Barbora')

@section('page')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h4 fw-bold mb-1"><i class="bi bi-clock-history"></i> Movimientos de stock</h1>
        <p class="text-muted mb-0">Todo lo que entró, salió o se ajustó. No se edita ni se borra.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('stock.index') }}" class="btn btn-light border">
            <i class="bi bi-boxes"></i> Existencias
        </a>
        <a href="{{ route('stock.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Nuevo movimiento
        </a>
    </div>
</div>

<form method="GET" class="row g-2 mb-3">
    <div class="col-md-3">
        <select name="product" class="form-select">
            <option value="">Todos los productos</option>
            @foreach($products as $product)
                <option value="{{ $product->id }}" {{ (string) request('product') === (string) $product->id ? 'selected' : '' }}>
                    {{ $product->name }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-md-2">
        <select name="warehouse" class="form-select">
            <option value="">Todos los almacenes</option>
            @foreach($warehouses as $warehouse)
                <option value="{{ $warehouse->id }}" {{ (string) request('warehouse') === (string) $warehouse->id ? 'selected' : '' }}>
                    {{ $warehouse->name }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-md-2">
        <select name="type" class="form-select">
            <option value="">Todos los tipos</option>
            @foreach(\App\Models\StockMovement::TYPES as $value => $label)
                <option value="{{ $value }}" {{ request('type') === $value ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-2">
        <input type="date" name="from" class="form-control" value="{{ request('from') }}" title="Desde">
    </div>
    <div class="col-md-2">
        <input type="date" name="to" class="form-control" value="{{ request('to') }}" title="Hasta">
    </div>
    <div class="col-md-1 d-flex gap-2">
        <button class="btn btn-outline-secondary flex-fill"><i class="bi bi-funnel"></i></button>
    </div>
</form>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Fecha</th>
                        <th>Producto</th>
                        <th>Tipo</th>
                        <th>Almacén</th>
                        <th>Registró</th>
                        <th class="text-end">Cantidad</th>
                        <th class="text-end pe-3">Queda</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($movements as $movement)
                        <tr>
                            <td class="ps-3 text-nowrap">{{ $movement->created_at->format('d/m/Y H:i') }}</td>
                            <td>
                                <a href="{{ route('products.show', $movement->product_id) }}" class="text-decoration-none">
                                    {{ $movement->product?->name }}
                                </a>
                                @if($movement->reference)
                                    <small class="text-muted d-block">{{ $movement->reference }}</small>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-{{ $movement->typeColor() }}">{{ $movement->typeLabel() }}</span>
                                @if($movement->reasonLabel())
                                    <small class="text-muted d-block">{{ $movement->reasonLabel() }}</small>
                                @endif
                            </td>
                            <td>{{ $movement->warehouse?->name ?? '—' }}</td>
                            <td>{{ $movement->creator?->name ?? '—' }}</td>
                            <td class="text-end fw-semibold text-{{ $movement->isIncoming() ? 'success' : 'danger' }}">
                                {{ $movement->isIncoming() ? '+' : '' }}{{ rtrim(rtrim(number_format((float) $movement->quantity, 2), '0'), '.') }}
                            </td>
                            <td class="text-end pe-3">{{ rtrim(rtrim(number_format((float) $movement->quantity_after, 2), '0'), '.') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                No hay movimientos que coincidan con el filtro.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="d-flex justify-content-center mt-3">
    {{ $movements->links() }}
</div>
@endsection
