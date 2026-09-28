@extends('layouts.app')

@section('title', 'Movimiento de stock - Barbora')

@section('page')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h4 fw-bold mb-1"><i class="bi bi-arrow-left-right"></i> Movimiento de stock</h1>
        <p class="text-muted mb-0">Entradas, salidas y ajustes tras un recuento.</p>
    </div>
    <a href="{{ route('stock.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Volver
    </a>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <form action="{{ route('stock.store') }}" method="POST">
                    @csrf

                    <label class="form-label">Tipo de movimiento</label>
                    <div class="btn-group w-100 mb-3" role="group">
                        @foreach(\App\Models\StockMovement::TYPES as $value => $label)
                            <input type="radio" class="btn-check movement-type" name="type" id="type-{{ $value }}"
                                   value="{{ $value }}" {{ old('type', $type) === $value ? 'checked' : '' }} required>
                            <label class="btn btn-outline-{{ ['entrada' => 'success', 'salida' => 'danger', 'ajuste' => 'secondary'][$value] }}"
                                   for="type-{{ $value }}">{{ $label }}</label>
                        @endforeach
                    </div>

                    <div class="row g-3">
                        <div class="col-md-7">
                            <label for="product_id" class="form-label">Producto</label>
                            <select id="product_id" name="product_id" class="form-select @error('product_id') is-invalid @enderror" required>
                                <option value="">Elegir producto</option>
                                @foreach($products as $product)
                                    <option value="{{ $product->id }}"
                                            data-unit="{{ $product->unitLabel() }}"
                                            {{ (string) old('product_id', $selectedProduct) === (string) $product->id ? 'selected' : '' }}>
                                        {{ $product->name }}{{ $product->sku ? ' · '.$product->sku : '' }}
                                    </option>
                                @endforeach
                            </select>
                            @error('product_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            @if($products->isEmpty())
                                <small class="text-muted">
                                    No hay productos con control de existencias.
                                    <a href="{{ route('products.create') }}">Crea el primero</a>.
                                </small>
                            @endif
                        </div>

                        <div class="col-md-5">
                            <label for="warehouse_id" class="form-label">Almacén</label>
                            <select id="warehouse_id" name="warehouse_id" class="form-select @error('warehouse_id') is-invalid @enderror" required>
                                <option value="">Elegir almacén</option>
                                @foreach($warehouses as $warehouse)
                                    <option value="{{ $warehouse->id }}"
                                            {{ (string) old('warehouse_id') === (string) $warehouse->id ? 'selected' : '' }}>
                                        {{ $warehouse->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('warehouse_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-5">
                            <label for="quantity" class="form-label" id="quantity-label">Cantidad</label>
                            <input type="number" step="0.01" min="0" id="quantity" name="quantity"
                                   class="form-control @error('quantity') is-invalid @enderror"
                                   value="{{ old('quantity') }}" required>
                            @error('quantity')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <small class="text-muted" id="quantity-hint"></small>
                        </div>

                        <div class="col-md-7">
                            <label for="reason" class="form-label">Motivo</label>
                            <select id="reason" name="reason" class="form-select">
                                <option value="">Sin especificar</option>
                                @foreach(\App\Models\StockMovement::REASONS as $value => $label)
                                    <option value="{{ $value }}" {{ old('reason') === $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-5">
                            <label for="unit_cost" class="form-label">Costo unitario</label>
                            <div class="input-group">
                                <span class="input-group-text">{{ \App\Support\Money::symbol($tenantCompany) }}</span>
                                <input type="number" step="0.01" min="0" id="unit_cost" name="unit_cost"
                                       class="form-control" value="{{ old('unit_cost') }}">
                            </div>
                            <small class="text-muted">Opcional, útil en compras.</small>
                        </div>

                        <div class="col-md-7">
                            <label for="reference" class="form-label">Referencia</label>
                            <input type="text" id="reference" name="reference" class="form-control"
                                   value="{{ old('reference') }}" placeholder="Nº de factura, proveedor…">
                        </div>

                        <div class="col-12">
                            <label for="notes" class="form-label">Notas</label>
                            <textarea id="notes" name="notes" rows="2" class="form-control">{{ old('notes') }}</textarea>
                        </div>

                        <div class="col-12">
                            <button class="btn btn-primary w-100">
                                <i class="bi bi-check-circle"></i> Registrar movimiento
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-3">Cómo funciona</h6>
                <p class="small mb-2">
                    <span class="badge bg-success">Entrada</span>
                    Suma al almacén: una compra, una devolución o un traslado recibido.
                </p>
                <p class="small mb-2">
                    <span class="badge bg-danger">Salida</span>
                    Resta: consumo en un servicio, una venta o una merma. Nunca deja el stock en negativo.
                </p>
                <p class="small mb-3">
                    <span class="badge bg-secondary">Ajuste</span>
                    Tras un recuento físico se escribe <strong>cuánto hay</strong>, no cuánto entra o sale.
                    El sistema calcula la diferencia.
                </p>
                <hr>
                <p class="text-muted small mb-0">
                    Los movimientos no se editan ni se borran: un error se corrige con otro ajuste.
                    Así el historial siempre explica por qué el stock es el que es.
                </p>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    // El ajuste pide una lectura de recuento, no una cantidad a mover: el
    // rótulo cambia para que no se confunda con una entrada.
    const label = document.getElementById('quantity-label');
    const hint = document.getElementById('quantity-hint');
    const types = Array.from(document.querySelectorAll('.movement-type'));

    function refresh() {
        const type = types.find((t) => t.checked)?.value;

        if (type === 'ajuste') {
            label.textContent = 'Cantidad contada';
            hint.textContent = 'Escribe cuánto hay realmente tras el recuento.';
        } else {
            label.textContent = 'Cantidad';
            hint.textContent = type === 'salida' ? 'Cuánto sale del almacén.' : 'Cuánto entra al almacén.';
        }
    }

    types.forEach((t) => t.addEventListener('change', refresh));
    refresh();
})();
</script>
@endpush
