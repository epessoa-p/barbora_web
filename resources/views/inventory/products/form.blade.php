<div class="card mt-4">
    <div class="card-body">
        <form action="{{ $product ? route('products.update', $product) : route('products.store') }}" method="POST">
            @csrf
            @if($product) @method('PUT') @endif

            <div class="row g-3">
                <div class="col-md-6">
                    <label for="name" class="form-label">Nombre</label>
                    <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror"
                           value="{{ old('name', $product?->name) }}" placeholder="Cera mate 100 ml" required>
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-3">
                    <label for="sku" class="form-label">Código</label>
                    <input type="text" id="sku" name="sku" class="form-control @error('sku') is-invalid @enderror"
                           value="{{ old('sku', $product?->sku) }}">
                    @error('sku')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-3">
                    <label for="product_category_id" class="form-label">Categoría</label>
                    <select id="product_category_id" name="product_category_id" class="form-select">
                        <option value="">Sin categoría</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}"
                                    {{ (string) old('product_category_id', $product?->product_category_id) === (string) $category->id ? 'selected' : '' }}>
                                {{ $category->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-12">
                    <label for="description" class="form-label">Descripción</label>
                    <input type="text" id="description" name="description" class="form-control"
                           value="{{ old('description', $product?->description) }}">
                </div>

                <div class="col-md-3">
                    <label for="unit" class="form-label">Unidad</label>
                    <select id="unit" name="unit" class="form-select" required>
                        @foreach(\App\Models\Product::UNITS as $value => $label)
                            <option value="{{ $value }}" {{ old('unit', $product?->unit ?? 'unidad') === $value ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3">
                    <label for="cost_price" class="form-label">Costo</label>
                    <div class="input-group">
                        <span class="input-group-text">{{ \App\Support\Money::symbol($tenantCompany) }}</span>
                        <input type="number" step="0.01" min="0" id="cost_price" name="cost_price"
                               class="form-control @error('cost_price') is-invalid @enderror"
                               value="{{ old('cost_price', $product?->cost_price ?? 0) }}" required>
                        @error('cost_price')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="col-md-3">
                    <label for="sale_price" class="form-label">Precio de venta</label>
                    <div class="input-group">
                        <span class="input-group-text">{{ \App\Support\Money::symbol($tenantCompany) }}</span>
                        <input type="number" step="0.01" min="0" id="sale_price" name="sale_price"
                               class="form-control @error('sale_price') is-invalid @enderror"
                               value="{{ old('sale_price', $product?->sale_price ?? 0) }}" required>
                        @error('sale_price')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <small class="text-muted" id="margin-hint"></small>
                </div>

                <div class="col-md-3">
                    <label for="min_stock" class="form-label">Stock mínimo</label>
                    <input type="number" step="0.01" min="0" id="min_stock" name="min_stock"
                           class="form-control @error('min_stock') is-invalid @enderror"
                           value="{{ old('min_stock', $product?->min_stock ?? 0) }}" required>
                    @error('min_stock')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <small class="text-muted">Avisa al bajar de aquí.</small>
                </div>

                <div class="col-12"><hr class="my-1"></div>

                <div class="col-md-4">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="is_sellable" name="is_sellable" value="1"
                               {{ old('is_sellable', $product?->is_sellable ?? true) ? 'checked' : '' }}>
                        <label class="form-check-label" for="is_sellable">Se vende al cliente</label>
                    </div>
                    <small class="text-muted">Desmárcalo si es solo insumo de uso interno.</small>
                </div>

                <div class="col-md-4">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="track_stock" name="track_stock" value="1"
                               {{ old('track_stock', $product?->track_stock ?? true) ? 'checked' : '' }}>
                        <label class="form-check-label" for="track_stock">Controlar existencias</label>
                    </div>
                    <small class="text-muted">Sin esto no se registran entradas ni salidas.</small>
                </div>

                <div class="col-md-4">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="active" name="active" value="1"
                               {{ old('active', $product?->active ?? true) ? 'checked' : '' }}>
                        <label class="form-check-label" for="active">Producto activo</label>
                    </div>
                </div>

                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle"></i> {{ $product ? 'Actualizar' : 'Crear producto' }}
                    </button>
                    <a href="{{ route('products.index') }}" class="btn btn-light border">
                        <i class="bi bi-arrow-left"></i> Cancelar
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
(function () {
    // El margen es lo que de verdad se mira al poner precio.
    const cost = document.getElementById('cost_price');
    const sale = document.getElementById('sale_price');
    const hint = document.getElementById('margin-hint');
    if (!cost || !sale || !hint) return;

    function refresh() {
        const c = parseFloat(cost.value) || 0;
        const s = parseFloat(sale.value) || 0;

        if (s <= 0) { hint.textContent = ''; return; }

        const margin = ((s - c) / s) * 100;
        hint.textContent = 'Margen: ' + margin.toFixed(1) + '%';
        hint.className = margin < 0 ? 'text-danger' : 'text-muted';
    }

    cost.addEventListener('input', refresh);
    sale.addEventListener('input', refresh);
    refresh();
})();
</script>
@endpush
