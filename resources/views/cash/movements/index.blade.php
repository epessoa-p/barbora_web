@extends('layouts.app')

@section('title', 'Movimientos de caja - Barbora')

@section('page')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h4 fw-bold mb-1"><i class="bi bi-list-ul"></i> Movimientos</h1>
        <p class="text-muted mb-0">Todo el dinero que entró y salió, de todos los turnos.</p>
    </div>
    <a href="{{ route('cash.current') }}" class="btn btn-primary">
        <i class="bi bi-cash-coin"></i> Caja actual
    </a>
</div>

<div class="row g-3 mb-4">
    @foreach([
        ['Ingresos', $income, 'bi-arrow-down-circle', '#16a34a'],
        ['Gastos', $expense, 'bi-arrow-up-circle', '#dc2626'],
        ['Resultado', $income - $expense, 'bi-calculator', '#2563eb'],
    ] as [$label, $value, $icon, $color])
        <div class="col-md-4">
            <div class="kpi-card">
                <div class="kpi-body">
                    <div>
                        <div class="kpi-value">{{ \App\Support\Money::format($value, $tenantCompany) }}</div>
                        <div class="kpi-label">{{ $label }}</div>
                    </div>
                    <div class="kpi-icon" style="background: {{ $color }};"><i class="bi {{ $icon }}"></i></div>
                </div>
            </div>
        </div>
    @endforeach
</div>

<form method="GET" class="row g-2 mb-3">
    <div class="col-md-3">
        <select name="type" class="form-select">
            <option value="">Ingresos y gastos</option>
            @foreach(\App\Models\CashMovement::TYPES as $value => $label)
                <option value="{{ $value }}" {{ request('type') === $value ? 'selected' : '' }}>Solo {{ strtolower($label) }}s</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3">
        <select name="method" class="form-select">
            <option value="">Todos los métodos</option>
            @foreach(\App\Models\CashMovement::paymentMethods() as $value => $label)
                <option value="{{ $value }}" {{ request('method') === $value ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-2">
        <input type="date" name="from" class="form-control" value="{{ request('from') }}" title="Desde">
    </div>
    <div class="col-md-2">
        <input type="date" name="to" class="form-control" value="{{ request('to') }}" title="Hasta">
    </div>
    <div class="col-md-2 d-flex gap-2">
        <button class="btn btn-outline-secondary flex-fill"><i class="bi bi-funnel"></i></button>
        @if(request()->hasAny(['type', 'method', 'from', 'to']))
            <a href="{{ route('cash.movements') }}" class="btn btn-light border">Limpiar</a>
        @endif
    </div>
</form>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Fecha</th>
                        <th>Concepto</th>
                        <th>Caja</th>
                        <th>Método</th>
                        <th>Registró</th>
                        <th class="text-end pe-3">Importe</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($movements as $movement)
                        <tr>
                            <td class="ps-3 text-nowrap">{{ $movement->created_at->format('d/m/Y H:i') }}</td>
                            <td>
                                <span class="fw-semibold">{{ $movement->concept }}</span>
                                @if($movement->reference)
                                    <small class="text-muted d-block">{{ $movement->reference }}</small>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('cash.sessions.show', $movement->cash_session_id) }}"
                                   class="text-decoration-none">{{ $movement->session?->caja?->name ?? '—' }}</a>
                            </td>
                            <td><span class="badge bg-light text-dark border">{{ $movement->paymentMethodLabel() }}</span></td>
                            <td>{{ $movement->creator?->name ?? '—' }}</td>
                            <td class="text-end pe-3 fw-semibold text-{{ $movement->isIncome() ? 'success' : 'danger' }}">
                                {{ $movement->isIncome() ? '+' : '−' }}
                                {{ \App\Support\Money::format($movement->amount, $tenantCompany) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                No hay movimientos que coincidan con el filtro.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="d-flex justify-content-center mt-3">
    {{ $movements->links() }}
</div>
@endsection
