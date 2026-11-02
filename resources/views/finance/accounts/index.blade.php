@extends('layouts.app')

@section('title', 'Cuentas - Barbora')

@section('page')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h4 fw-bold mb-1"><i class="bi bi-bank"></i> Cuentas</h1>
        <p class="text-muted mb-0">El dinero fuera del cajón: capital, caja chica, banco… De aquí salen gastos y pagos a proveedores.</p>
    </div>
    @if(auth()->user()->hasPermissionInCompany('accounts.create', $tenantCompany))
        <a href="{{ route('accounts.create') }}" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Nueva cuenta</a>
    @endif
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="kpi-card">
            <div class="kpi-body">
                <div>
                    <div class="kpi-value">{{ \App\Support\Money::format($total, $tenantCompany) }}</div>
                    <div class="kpi-label">Saldo de las cuentas activas</div>
                </div>
                <div class="kpi-icon" style="background:#16a34a"><i class="bi bi-wallet2"></i></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    @forelse($accounts as $account)
        <div class="col-md-6 col-xl-4">
            <a href="{{ route('accounts.show', $account) }}" class="card border-0 shadow-sm h-100 text-decoration-none text-reset {{ $account->active ? '' : 'opacity-50' }}">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <span class="badge bg-light text-dark border"><i class="bi {{ $account->icon() }}"></i> {{ $account->typeLabel() }}</span>
                        @unless($account->active)<span class="badge bg-secondary">Inactiva</span>@endunless
                    </div>
                    <div class="fw-bold">{{ $account->name }}</div>
                    <div class="small text-muted mb-3">
                        {{ $account->bank_name ? $account->bank_name.($account->account_number ? ' · '.$account->account_number : '') : ($account->branch?->name ?? 'Toda la barbería') }}
                    </div>
                    <div class="fs-4 fw-bold {{ (float) $account->balance < 0 ? 'text-danger' : '' }}">
                        {{ \App\Support\Money::format($account->balance, $tenantCompany) }}
                    </div>
                </div>
            </a>
        </div>
    @empty
        <div class="col-12">
            <div class="card border-0 shadow-sm"><div class="card-body text-center text-muted py-5">
                Todavía no hay cuentas. Crea la primera: el capital, la caja chica o tu cuenta de banco.
            </div></div>
        </div>
    @endforelse
</div>
@endsection
