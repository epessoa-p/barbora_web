@extends('layouts.app')

@section('title', 'Compra '.$purchase->number.' - Barbora')

@section('page')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h4 fw-bold mb-1">
            <i class="bi bi-bag-check"></i> Compra <code>{{ $purchase->number }}</code>
            <span class="badge bg-{{ $purchase->statusColor() }} fs-6 align-middle">{{ $purchase->statusLabel() }}</span>
        </h1>
        <p class="text-muted mb-0">
            {{ $purchase->purchased_on->format('d/m/Y') }}
            · {{ $purchase->supplier?->name ?? 'Sin proveedor' }}
            · {{ $purchase->warehouse?->name }}
            @if($purchase->invoice_number) · Factura {{ $purchase->invoice_number }} @endif
        </p>
    </div>
    <a href="{{ route('purchases.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Compras</a>
</div>

@if($errors->any())
    <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif

<div class="row g-4">
    <div class="col-lg-7">
        @if($purchase->isCancelled())
            <div class="alert alert-secondary">
                <strong>Anulada</strong> el {{ $purchase->cancelled_at->format('d/m/Y H:i') }}.
                @if($purchase->cancel_reason) Motivo: {{ $purchase->cancel_reason }}. @endif
                La mercadería salió del almacén y los pagos se devolvieron.
            </div>
        @endif

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-0">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr><th class="ps-3">Producto</th><th class="text-end">Cantidad</th><th class="text-end">Costo</th><th class="text-end pe-3">Total</th></tr>
                    </thead>
                    <tbody>
                        @foreach($purchase->items as $item)
                            <tr>
                                <td class="ps-3">{{ $item->product_name }}</td>
                                <td class="text-end">{{ rtrim(rtrim(number_format($item->quantity, 2, '.', ''), '0'), '.') }}</td>
                                <td class="text-end">{{ \App\Support\Money::format($item->unit_cost, $tenantCompany) }}</td>
                                <td class="text-end pe-3">{{ \App\Support\Money::format($item->total, $tenantCompany) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        @if((float) $purchase->discount > 0)
                            <tr><td colspan="3" class="text-end text-muted">Descuento</td><td class="text-end pe-3">−{{ \App\Support\Money::format($purchase->discount, $tenantCompany) }}</td></tr>
                        @endif
                        <tr class="fw-bold"><td colspan="3" class="text-end">Total</td><td class="text-end pe-3">{{ \App\Support\Money::format($purchase->total, $tenantCompany) }}</td></tr>
                        <tr><td colspan="3" class="text-end text-muted">Pagado</td><td class="text-end pe-3 text-success">{{ \App\Support\Money::format($purchase->paid, $tenantCompany) }}</td></tr>
                        <tr class="fw-bold"><td colspan="3" class="text-end">Se debe</td><td class="text-end pe-3 {{ $purchase->balance() > 0 ? 'text-danger' : '' }}">{{ \App\Support\Money::format($purchase->balance(), $tenantCompany) }}</td></tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <h6 class="fw-bold">Pagos</h6>
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <table class="table align-middle mb-0">
                    <tbody>
                        @forelse($purchase->payments as $payment)
                            <tr class="{{ $payment->cancelled_at ? 'opacity-50' : '' }}">
                                <td class="ps-3">{{ $payment->paid_on->format('d/m/Y') }}</td>
                                <td><i class="bi {{ $payment->source === 'cash' ? 'bi-cash-coin' : 'bi-bank' }}"></i> {{ $payment->sourceLabel() }}
                                    @if($payment->reference)<small class="text-muted"> · {{ $payment->reference }}</small>@endif</td>
                                <td class="text-end pe-3 fw-semibold">
                                    {{ \App\Support\Money::format($payment->amount, $tenantCompany) }}
                                    @if($payment->cancelled_at)<span class="badge bg-secondary ms-1">Devuelto</span>@endif
                                </td>
                            </tr>
                        @empty
                            <tr><td class="text-center text-muted py-4">Sin pagos: la compra está a crédito.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        @if(! $purchase->isCancelled() && $purchase->balance() > 0 && auth()->user()->hasPermissionInCompany('purchases.edit', $tenantCompany))
            <form action="{{ route('purchases.pay', $purchase) }}" method="POST" class="card border-0 shadow-sm mb-4">
                @csrf
                <div class="card-body p-4">
                    <h6 class="fw-bold">Registrar un pago</h6>
                    @if($purchase->due_on)
                        <p class="small {{ $purchase->due_on->isPast() ? 'text-danger' : 'text-muted' }}">Vence el {{ $purchase->due_on->format('d/m/Y') }}.</p>
                    @endif
                    <div class="row g-2 mb-3">
                        <div class="col-7">
                            <div class="input-group">
                                <span class="input-group-text">{{ \App\Support\Money::symbol($tenantCompany) }}</span>
                                <input type="number" step="0.01" min="0.01" max="{{ $purchase->balance() }}" name="amount" class="form-control" value="{{ old('amount', number_format($purchase->balance(), 2, '.', '')) }}" required>
                            </div>
                        </div>
                        <div class="col-5"><input type="date" name="paid_on" class="form-control" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" required></div>
                    </div>
                    <input type="text" name="reference" class="form-control mb-3" maxlength="255" placeholder="Referencia (opcional)">
                    @include('finance.partials.payment-source')
                    <button class="btn btn-primary w-100 mt-3"><i class="bi bi-cash"></i> Registrar pago</button>
                </div>
            </form>
        @endif

        @if(! $purchase->isCancelled() && auth()->user()->hasPermissionInCompany('purchases.delete', $tenantCompany))
            <form action="{{ route('purchases.cancel', $purchase) }}" method="POST" class="card border-0 shadow-sm"
                  onsubmit="return confirm('¿Anular la compra? La mercadería sale del almacén y los pagos se devuelven.')">
                @csrf
                @method('PATCH')
                <div class="card-body p-4">
                    <h6 class="fw-bold">Anular compra</h6>
                    <p class="small text-muted">Lo comprado sale del almacén y cada pago vuelve a su cuenta o a la caja. Si ya se vendió parte, no se podrá.</p>
                    <input type="text" name="cancel_reason" class="form-control mb-3" maxlength="255" placeholder="Motivo (opcional)" data-no-upper>
                    <button class="btn btn-outline-danger w-100"><i class="bi bi-x-circle"></i> Anular</button>
                </div>
            </form>
        @endif
    </div>
</div>
@endsection
