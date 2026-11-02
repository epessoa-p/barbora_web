@extends('layouts.app')

@section('title', 'Compras - Barbora')

@section('page')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h4 fw-bold mb-1"><i class="bi bi-bag-check"></i> Compras</h1>
        <p class="text-muted mb-0">Mercadería que entra al almacén, al contado o a crédito.</p>
    </div>
    @if(auth()->user()->hasPermissionInCompany('purchases.create', $tenantCompany))
        <a href="{{ route('purchases.create') }}" class="btn btn-primary"><i class="bi bi-bag-plus"></i> Nueva compra</a>
    @endif
</div>

<div class="row g-3 mb-4">
    <div class="col-md-6 col-xl-3">
        <div class="kpi-card"><div class="kpi-body">
            <div><div class="kpi-value">{{ \App\Support\Money::format($summary['total'], $tenantCompany) }}</div><div class="kpi-label">Comprado</div></div>
            <div class="kpi-icon" style="background:#0d9488"><i class="bi bi-bag"></i></div>
        </div></div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="kpi-card"><div class="kpi-body">
            <div><div class="kpi-value">{{ \App\Support\Money::format($summary['debt'], $tenantCompany) }}</div><div class="kpi-label">Por pagar a proveedores</div></div>
            <div class="kpi-icon" style="background:#dc2626"><i class="bi bi-hourglass-split"></i></div>
        </div></div>
    </div>
</div>

<form method="GET" class="row g-2 mb-3">
    <div class="col-md-2"><input type="date" name="from" class="form-control" value="{{ request('from') }}" title="Desde"></div>
    <div class="col-md-2"><input type="date" name="to" class="form-control" value="{{ request('to') }}" title="Hasta"></div>
    <div class="col-md-3">
        <select name="supplier" class="form-select">
            <option value="">Todos los proveedores</option>
            @foreach($suppliers as $supplier)
                <option value="{{ $supplier->id }}" {{ (string) request('supplier') === (string) $supplier->id ? 'selected' : '' }}>{{ $supplier->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3">
        <select name="status" class="form-select">
            <option value="">Todos los estados</option>
            <option value="deuda" {{ request('status') === 'deuda' ? 'selected' : '' }}>Con deuda</option>
            @foreach(\App\Models\Purchase::STATUSES as $value => $label)
                <option value="{{ $value }}" {{ request('status') === $value ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-2 d-flex gap-2">
        <button class="btn btn-outline-secondary flex-fill"><i class="bi bi-funnel"></i></button>
        @if(request()->hasAny(['from', 'to', 'supplier', 'status']))
            <a href="{{ route('purchases.index') }}" class="btn btn-light border">Limpiar</a>
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
                        <th>Proveedor</th>
                        <th>Almacén</th>
                        <th class="text-end">Total</th>
                        <th class="text-end">Debe</th>
                        <th class="text-center">Estado</th>
                        <th class="text-end pe-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($purchases as $purchase)
                        <tr class="{{ $purchase->isCancelled() ? 'opacity-50' : '' }}">
                            <td class="ps-3"><code>{{ $purchase->number }}</code></td>
                            <td class="text-nowrap">{{ $purchase->purchased_on->format('d/m/Y') }}</td>
                            <td>
                                {{ $purchase->supplier?->name ?? 'Sin proveedor' }}
                                @if($purchase->invoice_number)<small class="text-muted d-block">Factura {{ $purchase->invoice_number }}</small>@endif
                            </td>
                            <td>{{ $purchase->warehouse?->name }}</td>
                            <td class="text-end fw-semibold">{{ \App\Support\Money::format($purchase->total, $tenantCompany) }}</td>
                            <td class="text-end {{ $purchase->balance() > 0 ? 'text-danger fw-semibold' : 'text-muted' }}">
                                {{ \App\Support\Money::format($purchase->balance(), $tenantCompany) }}
                            </td>
                            <td class="text-center"><span class="badge bg-{{ $purchase->statusColor() }}">{{ $purchase->statusLabel() }}</span></td>
                            <td class="text-end pe-3">
                                <a href="{{ route('purchases.show', $purchase) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-5">No hay compras registradas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
<div class="d-flex justify-content-center mt-3">{{ $purchases->links() }}</div>
@endsection
