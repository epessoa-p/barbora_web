@extends('layouts.app')

@section('title', 'Productos - Barbora')

@section('page')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h4 fw-bold mb-1"><i class="bi bi-box"></i> Productos</h1>
        <p class="text-muted mb-0">Lo que se vende al cliente y los insumos de uso interno.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('product-categories.index') }}" class="btn btn-light border">
            <i class="bi bi-tags"></i> Categorías
        </a>
        @if($limit['reached'])
            <button class="btn btn-primary" disabled
                    title="Alcanzaste el límite de productos de tu plan">
                <i class="bi bi-plus-circle"></i> Nuevo producto
            </button>
        @else
            <a href="{{ route('products.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Nuevo producto
            </a>
        @endif
    </div>
</div>

@unless($limit['unlimited'])
    <div class="alert alert-{{ $limit['reached'] ? 'warning' : 'light border' }} d-flex justify-content-between align-items-center">
        <span>
            <i class="bi bi-box-seam"></i>
            Productos de tu plan: <strong>{{ $limit['usage'] }} de {{ $limit['max'] }}</strong>.
            @if($limit['reached'])
                Alcanzaste el límite; contacta a tu proveedor para ampliarlo.
            @endif
        </span>
    </div>
@endunless

<form method="GET" class="row g-2 mb-3">
    <div class="col-md-4">
        <input type="search" name="q" class="form-control" placeholder="Buscar por nombre o código…"
               value="{{ request('q') }}">
    </div>
    <div class="col-md-3">
        <select name="category" class="form-select">
            <option value="">Todas las categorías</option>
            @foreach($categories as $category)
                <option value="{{ $category->id }}" {{ (string) request('category') === (string) $category->id ? 'selected' : '' }}>
                    {{ $category->name }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3">
        <select name="filter" class="form-select">
            <option value="">Todos los productos</option>
            <option value="low" {{ request('filter') === 'low' ? 'selected' : '' }}>Solo stock bajo</option>
        </select>
    </div>
    <div class="col-md-2 d-flex gap-2">
        <button class="btn btn-outline-secondary flex-fill"><i class="bi bi-funnel"></i></button>
        @if(request()->hasAny(['q', 'category', 'filter']))
            <a href="{{ route('products.index') }}" class="btn btn-light border">Limpiar</a>
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
                        <th class="text-end">Costo</th>
                        <th class="text-end">Venta</th>
                        <th class="text-center">Stock</th>
                        <th class="text-center">Estado</th>
                        <th class="text-end pe-3">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($products as $product)
                        <tr>
                            <td class="ps-3">
                                <a href="{{ route('products.show', $product) }}" class="fw-semibold text-decoration-none">
                                    {{ $product->name }}
                                </a>
                                <small class="text-muted d-block">
                                    {{ $product->sku ?: $product->unitLabel() }}
                                    @unless($product->is_sellable)
                                        · <span class="badge bg-light text-dark border">Insumo</span>
                                    @endunless
                                </small>
                            </td>
                            <td>{{ $product->category?->name ?? '—' }}</td>
                            <td class="text-end">{{ \App\Support\Money::format($product->cost_price, $tenantCompany) }}</td>
                            <td class="text-end fw-semibold">
                                {{ \App\Support\Money::format($product->sale_price, $tenantCompany) }}
                                @if($product->marginPercent() !== null)
                                    <small class="text-muted d-block">{{ $product->marginPercent() }}% margen</small>
                                @endif
                            </td>
                            <td class="text-center">
                                @if(! $product->track_stock)
                                    <span class="text-muted">Sin control</span>
                                @else
                                    <span class="fw-semibold {{ $product->isLowStock() ? 'text-danger' : '' }}">
                                        {{ rtrim(rtrim(number_format($product->totalStock(), 2), '0'), '.') }}
                                    </span>
                                    @if($product->isLowStock())
                                        <i class="bi bi-exclamation-triangle-fill text-warning"
                                           title="Por debajo del mínimo ({{ rtrim(rtrim(number_format((float) $product->min_stock, 2), '0'), '.') }})"></i>
                                    @endif
                                @endif
                            </td>
                            <td class="text-center">
                                <span class="badge {{ $product->active ? 'bg-success' : 'bg-secondary' }}">
                                    {{ $product->active ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>
                            <td class="text-end pe-3">
                                @if($product->track_stock)
                                    <a href="{{ route('stock.create', ['product' => $product->id]) }}"
                                       class="btn btn-sm btn-outline-secondary" title="Mover stock">
                                        <i class="bi bi-arrow-left-right"></i>
                                    </a>
                                @endif
                                <a href="{{ route('products.edit', $product) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form action="{{ route('products.destroy', $product) }}" method="POST" class="d-inline"
                                      onsubmit="return confirm('¿Eliminar «{{ $product->name }}»?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                @if(request()->hasAny(['q', 'category', 'filter']))
                                    Ningún producto coincide con el filtro.
                                @else
                                    Todavía no hay productos.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="d-flex justify-content-center mt-3">
    {{ $products->links() }}
</div>
@endsection
