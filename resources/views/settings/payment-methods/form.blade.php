<div class="container-fluid" style="max-width: 620px;">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="h4 fw-bold mb-1">{{ $method ? 'Editar método de pago' : 'Nuevo método de pago' }}</h1>
            <p class="text-muted mb-0">Cómo cobra tu barbería.</p>
        </div>
        <a href="{{ route('payment-methods.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0 ps-3">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form action="{{ $action }}" method="POST">
        @csrf
        @if($method) @method('PUT') @endif

        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <div class="mb-4">
                    <label class="form-label">Nombre <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control"
                           value="{{ old('name', $method?->name) }}"
                           placeholder="Ej: Tigo Money, QR Banco Unión" required autofocus>
                    @if($method)
                        <small class="text-muted">
                            Se guarda como <code>{{ $method->slug }}</code> en las ventas.
                            Renombrarlo no toca el historial.
                        </small>
                    @endif
                </div>

                <div class="border rounded-3 p-3 mb-3 {{ old('counts_as_cash', $method?->counts_as_cash) ? 'border-success bg-success-subtle' : '' }}">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="counts_as_cash" id="counts_as_cash"
                               value="1" {{ old('counts_as_cash', $method?->counts_as_cash) ? 'checked' : '' }}>
                        <label class="form-check-label fw-semibold" for="counts_as_cash">
                            Este dinero entra al cajón
                        </label>
                    </div>
                    <small class="text-muted d-block mt-2">
                        Márcalo solo si el dinero queda físicamente en la caja. Es lo que se
                        compara al arquear el turno. Un cobro con tarjeta o por billetera va
                        al banco: si lo marcas, el arqueo saldrá descuadrado todos los días.
                    </small>
                </div>

                <div class="border rounded-3 p-3 mb-4">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="requires_reference" id="requires_reference"
                               value="1" {{ old('requires_reference', $method?->requires_reference) ? 'checked' : '' }}>
                        <label class="form-check-label fw-semibold" for="requires_reference">
                            Pedir número de operación al cobrar
                        </label>
                    </div>
                    <small class="text-muted d-block mt-2">
                        Útil en transferencias y QR, para poder cruzarlo luego con el extracto
                        del banco.
                    </small>
                </div>

                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="active" id="active"
                           value="1" {{ old('active', $method?->active ?? true) ? 'checked' : '' }}>
                    <label class="form-check-label" for="active">Activo</label>
                </div>
                <small class="text-muted">Un método de baja deja de aparecer al cobrar, pero no desaparece del historial.</small>
            </div>
        </div>

        <div class="mt-3 d-flex gap-2">
            <button class="btn btn-primary px-4" type="submit">
                <i class="bi bi-save"></i> {{ $method ? 'Guardar cambios' : 'Crear método' }}
            </button>
            <a href="{{ route('payment-methods.index') }}" class="btn btn-light border">Cancelar</a>
        </div>
    </form>
</div>
