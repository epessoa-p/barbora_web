{{--
    «Pendiente de pagar»: las comisiones que todavía no se liquidaron, de
    CUALQUIER fecha (App\Support\PendingCommissions). No depende del filtro,
    para que lo que se debe del mes pasado no quede escondido.

    «Pagar» abre el detalle del barbero con el rango que cubre todo lo
    pendiente, donde está el formulario «Liquidar y pagar».

    Parámetros: $owed (byPersonal), $owedSummary (summary), $hasRules (opcional).
--}}
@php
    $canPay = auth()->user()->hasPermissionInCompany('commissions.create', $tenantCompany);
@endphp

<div class="card border-0 shadow-sm mb-4 {{ $owedSummary['total'] > 0 ? 'border-start border-4 border-warning' : '' }}">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
            <div>
                <h6 class="fw-bold mb-0"><i class="bi bi-hourglass-split text-warning"></i> Pendiente de pagar</h6>
                <small class="text-muted">Todas las comisiones sin liquidar, de cualquier fecha.</small>
            </div>
            <div class="text-end">
                <div class="fs-4 fw-bold">{{ \App\Support\Money::format($owedSummary['total'], $tenantCompany) }}</div>
                <small class="text-muted">{{ $owedSummary['count'] }} {{ $owedSummary['count'] === 1 ? 'comisión' : 'comisiones' }} · {{ $owedSummary['people'] }} {{ $owedSummary['people'] === 1 ? 'persona' : 'personas' }}</small>
            </div>
        </div>

        @forelse($owed as $row)
            <div class="d-flex align-items-center gap-2 py-2 border-top">
                <div class="flex-fill">
                    <div class="fw-semibold">{{ $row['personal']->full_name }}</div>
                    <small class="text-muted">
                        {{ $row['count'] }} {{ $row['count'] === 1 ? 'comisión' : 'comisiones' }}
                        · desde el {{ $row['since']->format('d/m/Y') }}
                    </small>
                </div>
                <div class="fw-bold text-warning-emphasis text-nowrap">{{ \App\Support\Money::format($row['total'], $tenantCompany) }}</div>
                <a href="{{ route('commissions.show', [$row['personal'], 'from' => $row['since']->toDateString(), 'to' => now()->toDateString()]) }}#pagar"
                   class="btn btn-sm {{ $canPay ? 'btn-warning' : 'btn-light border' }}">
                    @if($canPay)<i class="bi bi-cash-coin"></i> Pagar @else Ver @endif
                </a>
            </div>
        @empty
            <div class="border-top pt-2 small text-muted">
                @if(isset($hasRules) && ! $hasRules)
                    Todavía no hay comisiones: para que se generen al cobrar, crea una regla en
                    <a href="{{ route('commission-rules.index') }}">Comisiones › Reglas</a>.
                @else
                    Nada pendiente: todas las comisiones están pagadas.
                @endif
            </div>
        @endforelse
    </div>
</div>
