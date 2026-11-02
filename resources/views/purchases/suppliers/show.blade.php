@extends('layouts.app')

@section('title', $supplier->name.' - Barbora')

@section('page')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h4 fw-bold mb-1"><i class="bi bi-truck"></i> {{ $supplier->name }}</h1>
        <p class="text-muted mb-0">
            {{ collect([$supplier->tax_id ? 'NIT '.$supplier->tax_id : null, $supplier->contact_name, $supplier->phone, $supplier->email])->filter()->join(' · ') ?: 'Sin datos de contacto' }}
        </p>
    </div>
    <div class="d-flex gap-2">
        @if(auth()->user()->hasPermissionInCompany('purchases.create', $tenantCompany) && $tenantCompany?->planAllows('inventario'))
            <a href="{{ route('purchases.create', ['supplier' => $supplier->id]) }}" class="btn btn-primary"><i class="bi bi-bag-plus"></i> Nueva compra</a>
        @endif
        @if(auth()->user()->hasPermissionInCompany('suppliers.edit', $tenantCompany))
            <a href="{{ route('suppliers.edit', $supplier) }}" class="btn btn-outline-primary"><i class="bi bi-pencil"></i> Editar</a>
        @endif
        <a href="{{ route('suppliers.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <div class="text-muted small">Se le debe</div>
                <div class="display-6 fw-bold {{ $debt > 0.004 ? 'text-danger' : '' }}">{{ \App\Support\Money::format($debt, $tenantCompany) }}</div>
                @if($supplier->address)<div class="small text-muted mt-2"><i class="bi bi-geo-alt"></i> {{ $supplier->address }}</div>@endif
                @if($supplier->notes)<div class="small mt-2">{{ $supplier->notes }}</div>@endif
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <h6 class="fw-bold">Compras</h6>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-0">
                <table class="table table-hover align-middle mb-0">
                    <tbody>
                        @forelse($purchases as $purchase)
                            <tr>
                                <td class="ps-3"><a href="{{ route('purchases.show', $purchase) }}"><code>{{ $purchase->number }}</code></a></td>
                                <td>{{ $purchase->purchased_on->format('d/m/Y') }}</td>
                                <td class="text-end">{{ \App\Support\Money::format($purchase->total, $tenantCompany) }}</td>
                                <td class="text-end pe-3"><span class="badge bg-{{ $purchase->statusColor() }}">{{ $purchase->statusLabel() }}</span></td>
                            </tr>
                        @empty
                            <tr><td class="text-center text-muted py-4">Sin compras.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if($expenses->isNotEmpty())
            <h6 class="fw-bold">Gastos pagados a este proveedor</h6>
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <table class="table table-hover align-middle mb-0">
                        <tbody>
                            @foreach($expenses as $expense)
                                <tr class="{{ $expense->isCancelled() ? 'opacity-50' : '' }}">
                                    <td class="ps-3"><code>{{ $expense->number }}</code></td>
                                    <td>{{ $expense->spent_on->format('d/m/Y') }}</td>
                                    <td>{{ $expense->description }}</td>
                                    <td class="text-end pe-3">{{ \App\Support\Money::format($expense->amount, $tenantCompany) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
