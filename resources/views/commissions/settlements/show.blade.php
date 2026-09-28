@extends('layouts.app')

@section('title', 'Liquidación '.$settlement->number.' - Barbora')

@section('page')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h4 fw-bold mb-1">Liquidación {{ $settlement->number }}</h1>
        <p class="text-muted mb-0">
            {{ $settlement->personal?->full_name }} · {{ $settlement->periodLabel() }}
        </p>
    </div>
    <a href="{{ route('commissions.settlements.index') }}" class="btn btn-outline-secondary">Volver</a>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="p-3 border-bottom">
                    <h6 class="fw-bold mb-0">Comisiones incluidas</h6>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Fecha</th>
                                <th>Concepto</th>
                                <th>Venta</th>
                                <th class="text-end pe-3">Comisión</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($settlement->entries as $entry)
                                <tr>
                                    <td class="ps-3 text-nowrap">{{ $entry->earned_at->format('d/m H:i') }}</td>
                                    <td>{{ $entry->item?->description ?? '—' }}</td>
                                    <td>
                                        <a href="{{ route('sales.show', $entry->sale_id) }}" class="text-decoration-none">
                                            {{ $entry->sale?->number }}
                                        </a>
                                    </td>
                                    <td class="text-end pe-3 fw-semibold">
                                        {{ \App\Support\Money::format($entry->amount, $tenantCompany) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-muted">
                                        Esta liquidación solo cubrió propinas.
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
                <h6 class="fw-bold mb-3">Importes</h6>
                <div class="d-flex justify-content-between py-1 border-bottom">
                    <span class="text-muted">Comisiones ({{ $settlement->entries_count }})</span>
                    <span>{{ \App\Support\Money::format($settlement->commissions_total, $tenantCompany) }}</span>
                </div>
                <div class="d-flex justify-content-between py-1 border-bottom">
                    <span class="text-muted">Propinas</span>
                    <span>{{ \App\Support\Money::format($settlement->tips_total, $tenantCompany) }}</span>
                </div>
                <div class="d-flex justify-content-between py-2 fw-bold fs-5">
                    <span>Pagado</span>
                    <span>{{ \App\Support\Money::format($settlement->total, $tenantCompany) }}</span>
                </div>
                <p class="text-muted small mb-0 mt-2">
                    Importes guardados el día del pago: no se recalculan aunque cambien las reglas.
                </p>
            </div>
        </div>

        <div class="card border-0 shadow-sm mt-4">
            <div class="card-body">
                <h6 class="fw-bold mb-3">Trazabilidad</h6>
                <p class="mb-1"><strong>Pagado el:</strong> {{ $settlement->paid_at->translatedFormat('d/m/Y H:i') }}</p>
                <p class="mb-1"><strong>Pagó:</strong> {{ $settlement->paidBy?->name ?? '—' }}</p>
                @if($settlement->cashSession)
                    <p class="mb-1">
                        <strong>Turno de caja:</strong>
                        <a href="{{ route('cash.sessions.show', $settlement->cashSession) }}">
                            {{ $settlement->cashSession->caja?->name }}
                        </a>
                    </p>
                @endif
                @if($settlement->notes)
                    <p class="mb-0"><strong>Notas:</strong> {{ $settlement->notes }}</p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
