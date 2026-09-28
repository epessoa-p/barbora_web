<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <h6 class="fw-bold mb-3"><i class="bi bi-plus-circle"></i> Registrar movimiento</h6>

        <form action="{{ route('cash.movements.store', $session) }}" method="POST">
            @csrf

            <div class="btn-group w-100 mb-3" role="group">
                @foreach(\App\Models\CashMovement::TYPES as $value => $label)
                    <input type="radio" class="btn-check" name="type" id="type-{{ $value }}" value="{{ $value }}"
                           {{ old('type', 'ingreso') === $value ? 'checked' : '' }} required>
                    <label class="btn btn-outline-{{ $value === 'ingreso' ? 'success' : 'danger' }}" for="type-{{ $value }}">
                        <i class="bi bi-arrow-{{ $value === 'ingreso' ? 'down' : 'up' }}-circle"></i> {{ $label }}
                    </label>
                @endforeach
            </div>

            <div class="mb-3">
                <label for="concept" class="form-label">Concepto</label>
                <input type="text" id="concept" name="concept" class="form-control @error('concept') is-invalid @enderror"
                       value="{{ old('concept') }}" placeholder="Cobro corte, compra de insumos…" required>
                @error('concept')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="row g-2 mb-3">
                <div class="col-7">
                    <label for="amount" class="form-label">Importe</label>
                    <div class="input-group">
                        <span class="input-group-text">{{ \App\Support\Money::symbol($tenantCompany) }}</span>
                        <input type="number" step="0.01" min="0.01" id="amount" name="amount"
                               class="form-control @error('amount') is-invalid @enderror"
                               value="{{ old('amount') }}" required>
                        @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-5">
                    <label for="payment_method" class="form-label">Método</label>
                    <select id="payment_method" name="payment_method" class="form-select">
                        @foreach(\App\Models\CashMovement::paymentMethods() as $value => $label)
                            <option value="{{ $value }}" {{ old('payment_method', 'efectivo') === $value ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label for="reference" class="form-label">Referencia</label>
                <input type="text" id="reference" name="reference" class="form-control"
                       value="{{ old('reference') }}" placeholder="Nº de comprobante, últimos dígitos…">
            </div>

            <button class="btn btn-primary w-100"><i class="bi bi-check-circle"></i> Registrar</button>

            <p class="text-muted small mb-0 mt-2">
                Solo el efectivo entra en el arqueo: tarjeta, transferencia y QR suman a la
                recaudación pero no al dinero del cajón.
            </p>
        </form>
    </div>
</div>
