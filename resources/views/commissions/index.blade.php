@extends('layouts.app')

@section('title', 'Comisiones - Barbora')

@section('page')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h4 fw-bold mb-1"><i class="bi bi-cash-stack"></i> Comisiones</h1>
        <p class="text-muted mb-0">
            Del {{ $from->translatedFormat('d/m/Y') }} al {{ $to->translatedFormat('d/m/Y') }}.
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('commission-rules.index') }}" class="btn btn-light border">
            <i class="bi bi-percent"></i> Reglas
        </a>
        <a href="{{ route('commissions.settlements.index') }}" class="btn btn-light border">
            <i class="bi bi-receipt"></i> Liquidaciones
        </a>
    </div>
</div>

<div class="row g-3 mb-4">
    @foreach([
        ['Devengado', $totals['earned'], 'bi-graph-up-arrow', '#2563eb'],
        ['Pendiente de pago', $totals['pending'], 'bi-hourglass-split', '#f59e0b'],
        ['Propinas', $totals['tips'], 'bi-coin', '#ca8a04'],
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
        <input type="date" name="from" class="form-control" value="{{ $from->toDateString() }}" title="Desde">
    </div>
    <div class="col-md-3">
        <input type="date" name="to" class="form-control" value="{{ $to->toDateString() }}" title="Hasta">
    </div>
    <div class="col-md-3 d-flex gap-2">
        <button class="btn btn-outline-secondary"><i class="bi bi-funnel"></i> Filtrar</button>
        <a href="{{ route('commissions.index') }}" class="btn btn-light border">Este mes</a>
    </div>
</form>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Barbero</th>
                        <th class="text-center">Líneas</th>
                        <th class="text-end">Devengado</th>
                        <th class="text-end">Propinas</th>
                        <th class="text-end">Pendiente</th>
                        <th class="text-end pe-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td class="ps-3">
                                <a href="{{ route('commissions.show', [$row['personal'], 'from' => $from->toDateString(), 'to' => $to->toDateString()]) }}"
                                   class="d-flex align-items-center gap-2 text-decoration-none">
                                    @if($row['personal']->photoUrl())
                                        <img src="{{ $row['personal']->photoUrl() }}" alt="" class="staff-avatar">
                                    @else
                                        <span class="staff-avatar staff-avatar-initials"
                                              style="background: {{ $row['personal']->agenda_color }};">
                                            {{ $row['personal']->initials() }}
                                        </span>
                                    @endif
                                    <span class="fw-semibold">{{ $row['personal']->full_name }}</span>
                                </a>
                            </td>
                            <td class="text-center">{{ $row['entries'] }}</td>
                            <td class="text-end">{{ \App\Support\Money::format($row['earned'], $tenantCompany) }}</td>
                            <td class="text-end text-muted">{{ \App\Support\Money::format($row['tips'], $tenantCompany) }}</td>
                            <td class="text-end fw-semibold {{ $row['pending'] > 0 ? 'text-warning-emphasis' : 'text-muted' }}">
                                {{ \App\Support\Money::format($row['pending'], $tenantCompany) }}
                                @if($row['pending_count'] > 0)
                                    <small class="text-muted d-block">{{ $row['pending_count'] }} sin liquidar</small>
                                @endif
                            </td>
                            <td class="text-end pe-3">
                                <a href="{{ route('commissions.show', [$row['personal'], 'from' => $from->toDateString(), 'to' => $to->toDateString()]) }}"
                                   class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-eye"></i> Detalle
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                Nadie devengó comisiones en este periodo.
                                @if(\App\Models\CommissionRule::where('active', true)->doesntExist())
                                    <br><a href="{{ route('commission-rules.create') }}">Crea una regla</a> para empezar.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
