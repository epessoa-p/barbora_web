@php
    $symbol = \App\Support\Money::symbol($tenantCompany);
    $byDenomination = \App\Support\CashSessionCloser::countsDenominations($tenantCompany);
    $denominations = \App\Support\CashSessionCloser::denominations($tenantCompany);

    // Lo cobrado por cada forma de pago, separando lo que está en el cajón
    // (entra al arqueo) de lo que está en el banco (solo informativo).
    $cashSlugs = \App\Models\PaymentMethod::cashSlugs();
    $methods = collect($session->byPaymentMethod())
        ->filter(fn ($row) => $row['count'] > 0)
        ->map(fn ($row, $slug) => $row + [
            'name' => \App\Models\PaymentMethod::labelFor($slug),
            'net' => $row['income'] - $row['expense'],
            'cash' => in_array($slug, $cashSlugs, true),
        ]);
    $otherMethods = $methods->where('cash', false);
    $otherTotal = $otherMethods->sum('net');

    $fmt = fn ($v) => rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
@endphp

<div class="card border-0 shadow-sm mt-4">
    <div class="card-body p-4">
        <h6 class="fw-bold mb-1"><i class="bi bi-lock"></i> Cerrar turno</h6>
        <p class="text-muted small mb-3">
            Solo se arquea el <strong>efectivo</strong>: es lo único que está en el cajón.
            Lo cobrado por otras formas de pago se muestra aparte y no se cuenta.
        </p>

        <div class="small fw-semibold text-uppercase text-muted mb-1">Efectivo (se cuenta)</div>
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

        <div class="small fw-semibold text-uppercase text-muted mt-3 mb-1">Otras formas de pago (no se cuentan)</div>
        @forelse($otherMethods as $row)
            <div class="d-flex justify-content-between py-1 border-bottom">
                <span class="text-muted">{{ $row['name'] }} <small>({{ $row['count'] }})</small></span>
                <span>{{ \App\Support\Money::format($row['net'], $tenantCompany) }}</span>
            </div>
        @empty
            <div class="text-muted small py-1">Ningún cobro por otras formas de pago en este turno.</div>
        @endforelse
        @if($otherMethods->isNotEmpty())
            <div class="d-flex justify-content-between py-2 fw-bold">
                <span>Total en otras formas</span>
                <span>{{ \App\Support\Money::format($otherTotal, $tenantCompany) }}</span>
            </div>
        @endif

        <form action="{{ route('cash.sessions.close', $session) }}" method="POST" class="mt-3"
              onsubmit="return confirm('¿Cerrar el turno? Después no se podrán registrar más movimientos.')">
            @csrf
            @method('PUT')

            @if($byDenomination)
                <label class="form-label fw-semibold">Billetes y monedas del cajón</label>
                @error('denominations')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror
                <div class="row g-2 mb-2" id="denominations">
                    @foreach(['bills' => 'Billetes', 'coins' => 'Monedas'] as $kind => $kindLabel)
                        @continue($denominations[$kind] === [])
                        <div class="col-12 small text-muted mt-2">{{ $kindLabel }}</div>
                        @foreach($denominations[$kind] as $value)
                            @php $key = \App\Support\CashSessionCloser::key($value); @endphp
                            <div class="col-6 col-md-4">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text" style="min-width: 76px">{{ $symbol }} {{ $fmt($value) }}</span>
                                    <span class="input-group-text">×</span>
                                    <input type="number" min="0" step="1" inputmode="numeric"
                                           name="denominations[{{ $key }}]" class="form-control denomination"
                                           data-value="{{ $value }}" value="{{ old('denominations.'.$key) }}" placeholder="0">
                                </div>
                            </div>
                        @endforeach
                    @endforeach
                </div>
                <div class="d-flex justify-content-between py-2 fw-bold border-top">
                    <span>Efectivo contado</span>
                    <span id="counted-total" data-expected="{{ $totals['expected_cash'] }}">{{ $symbol }} 0.00</span>
                </div>
                <small class="text-muted d-block mb-3" id="difference-hint"></small>
            @else
                <div class="mb-3">
                    <label for="closing_amount" class="form-label">Efectivo contado</label>
                    <div class="input-group">
                        <span class="input-group-text">{{ $symbol }}</span>
                        <input type="number" step="0.01" min="0" id="closing_amount" name="closing_amount"
                               class="form-control @error('closing_amount') is-invalid @enderror"
                               value="{{ old('closing_amount') }}"
                               data-expected="{{ $totals['expected_cash'] }}" required>
                        @error('closing_amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <small class="text-muted" id="difference-hint"></small>
                </div>
            @endif

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
    // Adelanta la diferencia mientras se cuenta: evita cerrar y descubrir
    // después que faltaba dinero.
    const hint = document.getElementById('difference-hint');
    if (!hint) return;

    const symbol = @json($symbol);

    function showDifference(counted, expected) {
        if (isNaN(counted)) { hint.textContent = ''; hint.className = 'text-muted'; return; }

        const diff = counted - expected;

        if (Math.abs(diff) < 0.01) {
            hint.textContent = 'Cuadra con lo esperado.';
            hint.className = 'text-success d-block mb-3';
        } else if (diff > 0) {
            hint.textContent = 'Sobran ' + symbol + ' ' + diff.toFixed(2) + '.';
            hint.className = 'text-info d-block mb-3';
        } else {
            hint.textContent = 'Faltan ' + symbol + ' ' + Math.abs(diff).toFixed(2) + '.';
            hint.className = 'text-danger d-block mb-3';
        }
    }

    // Por total: se compara lo escrito.
    const input = document.getElementById('closing_amount');
    if (input) {
        input.addEventListener('input', function () {
            showDifference(parseFloat(input.value), parseFloat(input.dataset.expected) || 0);
        });
        return;
    }

    // Por billetes y monedas: el total se suma solo (el servidor lo recalcula).
    const totalEl = document.getElementById('counted-total');
    const fields = Array.from(document.querySelectorAll('.denomination'));

    function refresh() {
        const total = fields.reduce(function (sum, f) {
            return sum + (parseInt(f.value, 10) || 0) * parseFloat(f.dataset.value);
        }, 0);
        totalEl.textContent = symbol + ' ' + total.toFixed(2);
        showDifference(fields.some((f) => f.value !== '') ? total : NaN, parseFloat(totalEl.dataset.expected) || 0);
    }

    fields.forEach((f) => f.addEventListener('input', refresh));
    refresh();
})();
</script>
@endpush
