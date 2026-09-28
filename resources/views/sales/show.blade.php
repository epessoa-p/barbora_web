@extends('layouts.app')

@section('title', 'Venta '.$sale->number.' - Barbora')

@section('page')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h4 fw-bold mb-1">
            Venta {{ $sale->number }}
            <span class="badge bg-{{ $sale->statusColor() }} align-middle">{{ $sale->statusLabel() }}</span>
        </h1>
        <p class="text-muted mb-0">
            {{ $sale->sold_at->translatedFormat('l d \d\e F \d\e Y, H:i') }}
            · {{ $sale->client?->full_name ?? 'Cliente de mostrador' }}
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('sales.receipt', $sale) }}" target="_blank" class="btn btn-light border">
            <i class="bi bi-printer"></i> Comprobante
        </a>
        <a href="{{ route('sales.index') }}" class="btn btn-outline-secondary">Volver</a>
    </div>
</div>

@if($sale->isCancelled())
    <div class="alert alert-danger">
        <i class="bi bi-x-circle-fill"></i>
        <strong>Venta anulada</strong> el {{ $sale->cancelled_at?->translatedFormat('d/m/Y H:i') }}
        por {{ $sale->canceller?->name ?? '—' }}.
        @if($sale->cancel_reason) Motivo: {{ $sale->cancel_reason }}. @endif
        Se devolvió el stock de los productos y se registró el egreso en caja.
    </div>
@endif

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="fw-bold mb-3">Detalle</h6>
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th class="ps-0">Concepto</th>
                            <th>Atendió</th>
                            <th class="text-center">Cant.</th>
                            <th class="text-end pe-0">Importe</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($sale->items as $item)
                            <tr>
                                <td class="ps-0">
                                    {{ $item->description }}
                                    <span class="badge bg-light text-dark border">{{ ucfirst($item->type) }}</span>
                                </td>
                                <td class="text-muted">{{ $item->personal?->full_name ?? '—' }}</td>
                                <td class="text-center">{{ rtrim(rtrim(number_format((float) $item->quantity, 2), '0'), '.') }}</td>
                                <td class="text-end pe-0">{{ \App\Support\Money::format($item->total, $tenantCompany) }}</td>
                            </tr>
                        @endforeach

                        <tr class="border-top">
                            <td class="ps-0 text-muted" colspan="3">Subtotal</td>
                            <td class="text-end pe-0">{{ \App\Support\Money::format($sale->subtotal, $tenantCompany) }}</td>
                        </tr>
                        @if((float) $sale->discount > 0)
                            <tr>
                                <td class="ps-0 text-muted" colspan="3">Descuento</td>
                                <td class="text-end pe-0 text-danger">
                                    − {{ \App\Support\Money::format($sale->discount, $tenantCompany) }}
                                </td>
                            </tr>
                        @endif
                        @if((float) $sale->tip > 0)
                            <tr>
                                <td class="ps-0 text-muted" colspan="3">
                                    Propina{{ $sale->personal ? ' para '.$sale->personal->full_name : '' }}
                                </td>
                                <td class="text-end pe-0">{{ \App\Support\Money::format($sale->tip, $tenantCompany) }}</td>
                            </tr>
                        @endif
                        <tr class="border-top">
                            <td class="ps-0 fw-bold" colspan="3">Total</td>
                            <td class="text-end pe-0 fw-bold fs-5">
                                {{ \App\Support\Money::format($sale->total, $tenantCompany) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        @if($sale->notes)
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-body">
                    <h6 class="fw-bold mb-2">Notas</h6>
                    <p class="mb-0">{{ $sale->notes }}</p>
                </div>
            </div>
        @endif
    </div>

    <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="fw-bold mb-3">Pagos</h6>
                @foreach($sale->payments as $payment)
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span>
                            {{ $payment->methodLabel() }}
                            @if($payment->reference)
                                <small class="text-muted d-block">{{ $payment->reference }}</small>
                            @endif
                        </span>
                        <span class="fw-semibold">{{ \App\Support\Money::format($payment->amount, $tenantCompany) }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="card border-0 shadow-sm mt-4">
            <div class="card-body">
                <h6 class="fw-bold mb-3">Trazabilidad</h6>
                <p class="mb-1"><strong>Atendió:</strong> {{ $sale->personal?->full_name ?? '—' }}</p>
                <p class="mb-1"><strong>Sucursal:</strong> {{ $sale->branch?->name ?? '—' }}</p>
                <p class="mb-1"><strong>Cobró:</strong> {{ $sale->creator?->name ?? '—' }}</p>
                @if($sale->cashSession)
                    <p class="mb-1">
                        <strong>Turno de caja:</strong>
                        <a href="{{ route('cash.sessions.show', $sale->cashSession) }}">
                            {{ $sale->cashSession->caja?->name }} · {{ $sale->cashSession->opened_at->format('d/m H:i') }}
                        </a>
                    </p>
                @endif
                @if($sale->appointment)
                    <p class="mb-0">
                        <strong>Nace de la cita:</strong>
                        <a href="{{ route('appointments.show', $sale->appointment) }}">
                            {{ $sale->appointment->starts_at->format('d/m/Y H:i') }}
                        </a>
                    </p>
                @endif
            </div>
        </div>

        @unless($sale->isCancelled())
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-body">
                    <h6 class="fw-bold mb-2">Anular</h6>
                    <p class="text-muted small mb-3">
                        Devuelve el stock de los productos y registra el egreso en el turno de caja
                        abierto. La venta no se borra: queda marcada como anulada.
                    </p>
                    <form action="{{ route('sales.cancel', $sale) }}" method="POST"
                          onsubmit="return confirm('¿Anular la venta {{ $sale->number }}?')">
                        @csrf
                        @method('PATCH')
                        <input type="text" name="cancel_reason" class="form-control form-control-sm mb-2"
                               placeholder="Motivo (opcional)">
                        <button class="btn btn-outline-danger w-100">
                            <i class="bi bi-x-circle"></i> Anular venta
                        </button>
                    </form>
                </div>
            </div>
        @endunless
    </div>
</div>
@endsection
