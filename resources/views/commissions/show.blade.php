@extends('layouts.app')

@section('title', 'Comisiones de '.$personal->full_name.' - Barbora')

@php
    $pendingTotal = (float) $pending->sum('amount');
@endphp

@section('page')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div class="d-flex align-items-center gap-3">
        @if($personal->photoUrl())
            <img src="{{ $personal->photoUrl() }}" alt="" class="staff-avatar staff-avatar-lg">
        @else
            <span class="staff-avatar staff-avatar-lg staff-avatar-initials"
                  style="background: {{ $personal->agenda_color }};">{{ $personal->initials() }}</span>
        @endif
        <div>
            <h1 class="h4 fw-bold mb-1">{{ $personal->full_name }}</h1>
            <p class="text-muted mb-0">
                Del {{ $from->translatedFormat('d/m/Y') }} al {{ $to->translatedFormat('d/m/Y') }}
            </p>
        </div>
    </div>
    <a href="{{ route('commissions.index', ['from' => $from->toDateString(), 'to' => $to->toDateString()]) }}"
       class="btn btn-outline-secondary">Volver</a>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="p-3 border-bottom">
                    <h6 class="fw-bold mb-0">Comisiones devengadas</h6>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Fecha</th>
                                <th>Concepto</th>
                                <th class="text-end">Base</th>
                                <th class="text-center">Tasa</th>
                                <th class="text-end">Comisión</th>
                                <th class="text-center pe-3">Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($entries as $entry)
                                <tr>
                                    <td class="ps-3 text-nowrap">{{ $entry->earned_at->format('d/m H:i') }}</td>
                                    <td>
                                        {{ $entry->item?->description ?? '—' }}
                                        <small class="text-muted d-block">
                                            <a href="{{ route('sales.show', $entry->sale_id) }}" class="text-decoration-none">
                                                {{ $entry->sale?->number }}
                                            </a>
                                        </small>
                                    </td>
                                    <td class="text-end text-muted">
                                        {{ \App\Support\Money::format($entry->base_amount, $tenantCompany) }}
                                    </td>
                                    <td class="text-center"><small>{{ $entry->rateLabel() }}</small></td>
                                    <td class="text-end fw-semibold">
                                        {{ \App\Support\Money::format($entry->amount, $tenantCompany) }}
                                    </td>
                                    <td class="text-center pe-3">
                                        @if($entry->isSettled())
                                            <a href="{{ route('commissions.settlements.show', $entry->commission_settlement_id) }}"
                                               class="badge bg-success text-decoration-none">Liquidada</a>
                                        @else
                                            <span class="badge bg-warning">Pendiente</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-5 text-muted">
                                        Sin comisiones en este periodo.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="fw-bold mb-3">A pagar</h6>

                <div class="d-flex justify-content-between py-1 border-bottom">
                    <span class="text-muted">Comisiones pendientes ({{ $pending->count() }})</span>
                    <span>{{ \App\Support\Money::format($pendingTotal, $tenantCompany) }}</span>
                </div>
                <div class="d-flex justify-content-between py-1 border-bottom">
                    <span class="text-muted">Propinas del periodo</span>
                    <span>{{ \App\Support\Money::format($tips, $tenantCompany) }}</span>
                </div>
                <div class="d-flex justify-content-between py-2 fw-bold fs-5">
                    <span>Total</span>
                    <span id="settle-total">
                        {{ \App\Support\Money::format($pendingTotal + $tips, $tenantCompany) }}
                    </span>
                </div>

                @if($pending->isEmpty() && $tips <= 0)
                    <p class="text-muted small mb-0 mt-2">Nada pendiente de liquidar en este periodo.</p>
                @else
                    <form action="{{ route('commissions.settle', $personal) }}" method="POST" class="mt-3"
                          onsubmit="return confirm('¿Liquidar y pagar a {{ $personal->full_name }}?')">
                        @csrf
                        <input type="hidden" name="period_start" value="{{ $from->toDateString() }}">
                        <input type="hidden" name="period_end" value="{{ $to->toDateString() }}">

                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" id="include_tips" name="include_tips"
                                   value="1" checked
                                   data-tips="{{ $tips }}" data-commissions="{{ $pendingTotal }}">
                            <label class="form-check-label" for="include_tips">
                                Pagar también las propinas
                            </label>
                        </div>

                        <textarea name="notes" rows="2" class="form-control form-control-sm mb-2"
                                  placeholder="Notas de la liquidación"></textarea>

                        @if(! $hasOpenCash)
                            <div class="alert alert-warning py-2 small">
                                <i class="bi bi-exclamation-triangle"></i>
                                No hay turno de caja abierto. Ábrelo antes de pagar.
                            </div>
                        @endif

                        <button class="btn btn-primary w-100" {{ $hasOpenCash ? '' : 'disabled' }}>
                            <i class="bi bi-cash-coin"></i> Liquidar y pagar
                        </button>
                        <p class="text-muted small mb-0 mt-2">
                            El pago sale del turno de caja abierto y queda registrado como egreso.
                        </p>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    // El total a pagar cambia si se decide no incluir las propinas.
    const toggle = document.getElementById('include_tips');
    const totalEl = document.getElementById('settle-total');
    if (!toggle || !totalEl) return;

    const symbol = @json(\App\Support\Money::symbol($tenantCompany));
    const commissions = parseFloat(toggle.dataset.commissions) || 0;
    const tips = parseFloat(toggle.dataset.tips) || 0;

    toggle.addEventListener('change', function () {
        const total = commissions + (this.checked ? tips : 0);
        totalEl.textContent = symbol + ' ' + total.toFixed(2);
    });
})();
</script>
@endpush
