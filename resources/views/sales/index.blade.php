@extends('layouts.app')

@section('title', 'Ventas - Barbora')

@section('page')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h4 fw-bold mb-1"><i class="bi bi-receipt"></i> Ventas</h1>
        <p class="text-muted mb-0">Historial de comandas cobradas.</p>
    </div>
    <a href="{{ route('sales.create') }}" class="btn btn-primary">
        <i class="bi bi-cart-plus"></i> Nueva venta
    </a>
</div>

<div class="row g-3 mb-4">
    @foreach([
        ['Ventas', $summary['count'], 'bi-receipt', '#2563eb', false],
        ['Facturado', $summary['total'], 'bi-cash-stack', '#16a34a', true],
        ['Propinas', $summary['tips'], 'bi-coin', '#ca8a04', true],
        ['Descuentos', $summary['discount'], 'bi-tag', '#dc2626', true],
    ] as [$label, $value, $icon, $color, $isMoney])
        <div class="col-xl-3 col-md-6">
            <div class="kpi-card">
                <div class="kpi-body">
                    <div>
                        <div class="kpi-value">
                            {{ $isMoney ? \App\Support\Money::format($value, $tenantCompany) : $value }}
                        </div>
                        <div class="kpi-label">{{ $label }}</div>
                    </div>
                    <div class="kpi-icon" style="background: {{ $color }};"><i class="bi {{ $icon }}"></i></div>
                </div>
            </div>
        </div>
    @endforeach
</div>

<form method="GET" class="row g-2 mb-3">
    <div class="col-md-2">
        <input type="date" name="from" class="form-control" value="{{ request('from') }}" title="Desde">
    </div>
    <div class="col-md-2">
        <input type="date" name="to" class="form-control" value="{{ request('to') }}" title="Hasta">
    </div>
    <div class="col-md-3">
        <select name="personal" class="form-select">
            <option value="">Todos los barberos</option>
            @foreach($staff as $person)
                <option value="{{ $person->id }}" {{ (string) request('personal') === (string) $person->id ? 'selected' : '' }}>
                    {{ $person->full_name }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3">
        <select name="status" class="form-select">
            <option value="">Todos los estados</option>
            @foreach(\App\Models\Sale::STATUSES as $value => $label)
                <option value="{{ $value }}" {{ request('status') === $value ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-2 d-flex gap-2">
        <button class="btn btn-outline-secondary flex-fill"><i class="bi bi-funnel"></i></button>
        @if(request()->hasAny(['from', 'to', 'personal', 'status']))
            <a href="{{ route('sales.index') }}" class="btn btn-light border">Limpiar</a>
        @endif
    </div>
</form>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Nº</th>
                        <th>Fecha</th>
                        <th>Cliente</th>
                        <th>Atendió</th>
                        <th>Pago</th>
                        <th class="text-end">Total</th>
                        <th class="text-center">Estado</th>
                        <th class="text-end pe-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($sales as $sale)
                        <tr class="{{ $sale->isCancelled() ? 'opacity-50' : '' }}">
                            <td class="ps-3"><code>{{ $sale->number }}</code></td>
                            <td class="text-nowrap">{{ $sale->sold_at->format('d/m/Y H:i') }}</td>
                            <td>{{ $sale->client?->full_name ?? 'Mostrador' }}</td>
                            <td>{{ $sale->personal?->full_name ?? '—' }}</td>
                            <td><span class="badge bg-light text-dark border">{{ $sale->paymentsLabel() }}</span></td>
                            <td class="text-end fw-semibold">
                                {{ \App\Support\Money::format($sale->total, $tenantCompany) }}
                                @if((float) $sale->tip > 0)
                                    <small class="text-muted d-block">
                                        incl. {{ \App\Support\Money::format($sale->tip, $tenantCompany) }} propina
                                    </small>
                                @endif
                            </td>
                            <td class="text-center">
                                <span class="badge bg-{{ $sale->statusColor() }}">{{ $sale->statusLabel() }}</span>
                            </td>
                            <td class="text-end pe-3 text-nowrap">
                                <a href="{{ route('sales.receipt', $sale) }}" target="_blank"
                                   class="btn btn-sm btn-outline-secondary" title="Comprobante">
                                    <i class="bi bi-printer"></i>
                                </a>
                                <a href="{{ route('sales.show', $sale) }}" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                @if(request()->hasAny(['from', 'to', 'personal', 'status']))
                                    Ninguna venta coincide con el filtro.
                                @else
                                    Todavía no hay ventas registradas.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="d-flex justify-content-center mt-3">
    {{ $sales->links() }}
</div>
@endsection
