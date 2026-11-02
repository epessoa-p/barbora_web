@extends('layouts.app')

@section('title', 'Gasto '.$expense->number.' - Barbora')

@section('page')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h4 fw-bold mb-1"><i class="bi bi-receipt-cutoff"></i> Gasto <code>{{ $expense->number }}</code></h1>
        <p class="text-muted mb-0">{{ $expense->spent_on->translatedFormat('d \d\e F \d\e Y') }}</p>
    </div>
    <a href="{{ route('expenses.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Gastos</a>
</div>

@if($errors->any())
    <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                @if($expense->isCancelled())
                    <div class="alert alert-secondary">
                        <strong>Anulado</strong> el {{ $expense->cancelled_at->format('d/m/Y H:i') }}.
                        @if($expense->cancel_reason) Motivo: {{ $expense->cancel_reason }}. @endif
                        El dinero volvió a {{ $expense->source === 'cash' ? 'la caja' : 'la cuenta' }}.
                    </div>
                @endif
                <div class="fs-3 fw-bold mb-1">{{ \App\Support\Money::format($expense->amount, $tenantCompany) }}</div>
                <div class="fw-semibold mb-3">{{ $expense->description }}</div>

                <dl class="row mb-0">
                    <dt class="col-sm-4 text-muted fw-normal">Categoría</dt><dd class="col-sm-8">{{ $expense->category?->name }}</dd>
                    @if($expense->payeeName())
                        <dt class="col-sm-4 text-muted fw-normal">Pagado a</dt><dd class="col-sm-8">{{ $expense->payeeName() }}</dd>
                    @endif
                    <dt class="col-sm-4 text-muted fw-normal">Salió de</dt>
                    <dd class="col-sm-8"><i class="bi {{ $expense->source === 'cash' ? 'bi-cash-coin' : 'bi-bank' }}"></i> {{ $expense->sourceLabel() }}</dd>
                    @if($expense->branch)<dt class="col-sm-4 text-muted fw-normal">Sucursal</dt><dd class="col-sm-8">{{ $expense->branch->name }}</dd>@endif
                    @if($expense->reference)<dt class="col-sm-4 text-muted fw-normal">Referencia</dt><dd class="col-sm-8">{{ $expense->reference }}</dd>@endif
                    @if($expense->notes)<dt class="col-sm-4 text-muted fw-normal">Notas</dt><dd class="col-sm-8">{{ $expense->notes }}</dd>@endif
                    <dt class="col-sm-4 text-muted fw-normal">Registró</dt><dd class="col-sm-8">{{ $expense->creator?->name ?? '—' }}</dd>
                </dl>
            </div>
        </div>
    </div>

    @if(! $expense->isCancelled() && auth()->user()->hasPermissionInCompany('expenses.delete', $tenantCompany))
        <div class="col-lg-5">
            <form action="{{ route('expenses.cancel', $expense) }}" method="POST" class="card border-0 shadow-sm"
                  onsubmit="return confirm('¿Anular este gasto? El dinero vuelve a {{ $expense->source === 'cash' ? 'la caja' : 'la cuenta' }}.')">
                @csrf
                @method('PATCH')
                <div class="card-body p-4">
                    <h6 class="fw-bold">Anular gasto</h6>
                    <p class="small text-muted">
                        No se borra: queda anulado y el dinero vuelve a {{ $expense->source === 'cash' ? 'la caja (como ingreso en el turno abierto)' : 'la cuenta' }}.
                    </p>
                    <input type="text" name="cancel_reason" class="form-control mb-3" maxlength="255" placeholder="Motivo (opcional)" data-no-upper>
                    <button class="btn btn-outline-danger w-100"><i class="bi bi-x-circle"></i> Anular</button>
                </div>
            </form>
        </div>
    @endif
</div>
@endsection
