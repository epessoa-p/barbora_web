<div class="card border-0 shadow-sm mt-4">
    <div class="card-body p-4">
        <h6 class="fw-bold mb-1"><i class="bi bi-lock"></i> Cerrar turno</h6>
        <p class="text-muted small mb-3">
            Cuenta el efectivo del cajón y escríbelo. La diferencia con lo esperado queda
            guardada en el arqueo.
        </p>

        <div class="d-flex justify-content-between py-1 border-bottom">
            <span class="text-muted">Fondo inicial</span>
            <span>{{ \App\Support\Money::format($session->opening_amount, $tenantCompany) }}</span>
        </div>
        <div class="d-flex justify-content-between py-1 border-bottom">
            <span class="text-muted">+ Efectivo cobrado</span>
            <span class="text-success">{{ \App\Support\Money::format($totals['cash_income'], $tenantCompany) }}</span>
        </div>
        <div class="d-flex justify-content-between py-1 border-bottom">
            <span class="text-muted">− Efectivo pagado</span>
            <span class="text-danger">{{ \App\Support\Money::format($totals['cash_expense'], $tenantCompany) }}</span>
        </div>
        <div class="d-flex justify-content-between py-2 fw-bold">
            <span>Efectivo esperado</span>
            <span>{{ \App\Support\Money::format($totals['expected_cash'], $tenantCompany) }}</span>
        </div>

        <form action="{{ route('cash.sessions.close', $session) }}" method="POST" class="mt-3"
              onsubmit="return confirm('¿Cerrar el turno? Después no se podrán registrar más movimientos.')">
            @csrf
            @method('PUT')

            <div class="mb-3">
                <label for="closing_amount" class="form-label">Efectivo contado</label>
                <div class="input-group">
                    <span class="input-group-text">{{ \App\Support\Money::symbol($tenantCompany) }}</span>
                    <input type="number" step="0.01" min="0" id="closing_amount" name="closing_amount"
                           class="form-control @error('closing_amount') is-invalid @enderror"
                           value="{{ old('closing_amount') }}"
                           data-expected="{{ $totals['expected_cash'] }}" required>
                    @error('closing_amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <small class="text-muted" id="difference-hint"></small>
            </div>

            <div class="mb-3">
                <label for="closing_notes" class="form-label">Observaciones</label>
                <textarea id="closing_notes" name="closing_notes" rows="2" class="form-control">{{ old('closing_notes') }}</textarea>
            </div>

            <button class="btn btn-outline-danger w-100"><i class="bi bi-lock"></i> Cerrar turno</button>
        </form>
    </div>
</div>

@push('scripts')
<script>
(function () {
    // Adelanta la diferencia mientras se escribe: evita cerrar y descubrir
    // después que faltaba dinero.
    const input = document.getElementById('closing_amount');
    const hint = document.getElementById('difference-hint');
    if (!input || !hint) return;

    const symbol = @json(\App\Support\Money::symbol($tenantCompany));

    input.addEventListener('input', function () {
        const expected = parseFloat(input.dataset.expected) || 0;
        const counted = parseFloat(input.value);

        if (isNaN(counted)) { hint.textContent = ''; hint.className = 'text-muted'; return; }

        const diff = counted - expected;

        if (Math.abs(diff) < 0.01) {
            hint.textContent = 'Cuadra con lo esperado.';
            hint.className = 'text-success';
        } else if (diff > 0) {
            hint.textContent = 'Sobran ' + symbol + ' ' + diff.toFixed(2) + '.';
            hint.className = 'text-info';
        } else {
            hint.textContent = 'Faltan ' + symbol + ' ' + Math.abs(diff).toFixed(2) + '.';
            hint.className = 'text-danger';
        }
    });
})();
</script>
@endpush
