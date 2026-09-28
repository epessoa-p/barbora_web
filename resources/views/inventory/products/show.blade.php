@extends('layouts.app')

@section('title', $product->name.' - Barbora')

@section('page')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h4 fw-bold mb-1">
            {{ $product->name }}
            @unless($product->active) <span class="badge bg-secondary align-middle">Inactivo</span> @endunless
            @unless($product->is_sellable) <span class="badge bg-light text-dark border align-middle">Insumo</span> @endunless
        </h1>
        <p class="text-muted mb-0">
            {{ $product->category?->name ?? 'Sin categoría' }}
            @if($product->sku) · {{ $product->sku }} @endif
        </p>
    </div>
    <div class="d-flex gap-2">
        @if($product->track_stock)
            <a href="{{ route('stock.create', ['product' => $product->id]) }}" class="btn btn-light border">
                <i class="bi bi-arrow-left-right"></i> Mover stock
            </a>
        @endif
        <a href="{{ route('products.edit', $product) }}" class="btn btn-primary"><i class="bi bi-pencil"></i> Editar</a>
        <a href="{{ route('products.index') }}" class="btn btn-outline-secondary">Volver</a>
    </div>
</div>

@if($product->isLowStock())
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <strong>Stock bajo:</strong> quedan {{ $product->formatQuantity($product->totalStock()) }}
        y el mínimo es {{ $product->formatQuantity((float) $product->min_stock) }}.
    </div>
@endif

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="fw-bold mb-3">Datos</h6>
                <p class="mb-1"><strong>Unidad:</strong> {{ $product->unitLabel() }}</p>
                <p class="mb-1"><strong>Costo:</strong> {{ \App\Support\Money::format($product->cost_price, $tenantCompany) }}</p>
                <p class="mb-1">
                    <strong>Precio de venta:</strong> {{ \App\Support\Money::format($product->sale_price, $tenantCompany) }}
                    @if($product->marginPercent() !== null)
                        <small class="text-muted">({{ $product->marginPercent() }}% de margen)</small>
                    @endif
                </p>
                <p class="mb-1"><strong>Stock mínimo:</strong> {{ rtrim(rtrim(number_format((float) $product->min_stock, 2), '0'), '.') }}</p>
                <p class="mb-0"><strong>Descripción:</strong> {{ $product->description ?: '—' }}</p>
            </div>
        </div>

        @if($product->track_stock)
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-body">
                    <h6 class="fw-bold mb-3">Existencias por almacén</h6>
                    @forelse($product->stocks as $stock)
                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                            <span>{{ $stock->warehouse?->name ?? '—' }}</span>
                            <span class="fw-semibold {{ $stock->isLow() ? 'text-danger' : '' }}">
                                {{ $product->formatQuantity((float) $stock->quantity) }}
                            </span>
                        </div>
                    @empty
                        <p class="text-muted mb-0">Sin existencias registradas todavía.</p>
                    @endforelse

                    <div class="d-flex justify-content-between align-items-center pt-2 fw-bold">
                        <span>Total</span>
                        <span>{{ $product->formatQuantity($product->totalStock()) }}</span>
                    </div>
                </div>
            </div>
        @endif
    </div>

    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="p-3 border-bottom">
                    <h6 class="fw-bold mb-0">Movimientos</h6>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Fecha</th>
                                <th>Tipo</th>
                                <th>Almacén</th>
                                <th class="text-end">Cantidad</th>
                                <th class="text-end pe-3">Queda</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($product->movements->take(25) as $movement)
                                <tr>
                                    <td class="ps-3 text-nowrap">{{ $movement->created_at->format('d/m/Y H:i') }}</td>
                                    <td>
                                        <span class="badge bg-{{ $movement->typeColor() }}">{{ $movement->typeLabel() }}</span>
                                        @if($movement->reasonLabel())
                                            <small class="text-muted d-block">{{ $movement->reasonLabel() }}</small>
                                        @endif
                                    </td>
                                    <td>{{ $movement->warehouse?->name ?? '—' }}</td>
                                    <td class="text-end fw-semibold text-{{ $movement->isIncoming() ? 'success' : 'danger' }}">
                                        {{ $movement->isIncoming() ? '+' : '' }}{{ rtrim(rtrim(number_format((float) $movement->quantity, 2), '0'), '.') }}
                                    </td>
                                    <td class="text-end pe-3">{{ rtrim(rtrim(number_format((float) $movement->quantity_after, 2), '0'), '.') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center py-5 text-muted">Sin movimientos todavía.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
