@extends('layouts.app')

@section('title', $account->name.' - Barbora')

@php
    $canMove = auth()->user()->hasPermissionInCompany('account_movements.create', $tenantCompany) && $account->active;
    $canCancel = auth()->user()->hasPermissionInCompany('account_movements.delete', $tenantCompany);
    $symbol = \App\Support\Money::symbol($tenantCompany);
@endphp

@section('page')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h4 fw-bold mb-1"><i class="bi {{ $account->icon() }}"></i> {{ $account->name }}</h1>
        <p class="text-muted mb-0">
            {{ $account->typeLabel() }}
            @if($account->bank_name) · {{ $account->bank_name }} @endif
            @if($account->account_number) · {{ $account->account_number }} @endif
            @if($account->branch) · {{ $account->branch->name }} @endif
            @unless($account->active) · <span class="badge bg-secondary">Inactiva</span> @endunless
        </p>
    </div>
    <div class="d-flex gap-2">
        @if(auth()->user()->hasPermissionInCompany('accounts.edit', $tenantCompany))
            <a href="{{ route('accounts.edit', $account) }}" class="btn btn-outline-primary"><i class="bi bi-pencil"></i> Editar</a>
        @endif
        <a href="{{ route('accounts.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Cuentas</a>
    </div>
</div>

@if($errors->any())
    <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <div class="text-muted small">Saldo</div>
                <div class="display-6 fw-bold {{ (float) $account->balance < 0 ? 'text-danger' : '' }}">
                    {{ \App\Support\Money::format($account->balance, $tenantCompany) }}
                </div>
            </div>
        </div>

        @if($canMove)
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <ul class="nav nav-pills nav-fill mb-3" role="tablist">
                        <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#mv-move" type="button">Ingreso / egreso</button></li>
                        <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#mv-transfer" type="button" {{ $otherAccounts->isEmpty() ? 'disabled' : '' }}>Transferir</button></li>
                    </ul>
                    <div class="tab-content">
                        <form class="tab-pane fade show active" id="mv-move" action="{{ route('accounts.movements.store', $account) }}" method="POST">
                            @csrf
                            <div class="btn-group w-100 mb-3" role="group">
                                <input type="radio" class="btn-check" name="type" id="type-in" value="ingreso" checked>
                                <label class="btn btn-outline-success" for="type-in"><i class="bi bi-plus-lg"></i> Ingreso</label>
                                <input type="radio" class="btn-check" name="type" id="type-out" value="egreso">
                                <label class="btn btn-outline-danger" for="type-out"><i class="bi bi-dash-lg"></i> Egreso</label>
                            </div>
                            <div class="input-group mb-2">
                                <span class="input-group-text">{{ $symbol }}</span>
                                <input type="number" step="0.01" min="0.01" name="amount" class="form-control" placeholder="Importe" required>
                            </div>
                            <input type="text" name="concept" class="form-control mb-2" maxlength="255" placeholder="Concepto (aporte de capital, depósito…)" required data-no-upper>
                            <div class="row g-2 mb-3">
                                <div class="col-6"><input type="date" name="occurred_on" class="form-control" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" required></div>
                                <div class="col-6"><input type="text" name="reference" class="form-control" placeholder="Referencia"></div>
                            </div>
                            <button class="btn btn-primary w-100">Registrar</button>
                        </form>

                        <form class="tab-pane fade" id="mv-transfer" action="{{ route('accounts.transfer', $account) }}" method="POST">
                            @csrf
                            <label class="form-label small">Pasar dinero a</label>
                            <select name="to_account_id" class="form-select mb-2" required>
                                @foreach($otherAccounts as $other)
                                    <option value="{{ $other->id }}">{{ $other->name }} ({{ $other->typeLabel() }})</option>
                                @endforeach
                            </select>
                            <div class="input-group mb-2">
                                <span class="input-group-text">{{ $symbol }}</span>
                                <input type="number" step="0.01" min="0.01" name="amount" class="form-control" placeholder="Importe" required>
                            </div>
                            <input type="text" name="concept" class="form-control mb-2" maxlength="255" placeholder="Concepto (opcional)" data-no-upper>
                            <input type="date" name="occurred_on" class="form-control mb-3" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" required>
                            <button class="btn btn-primary w-100">Transferir</button>
                        </form>
                    </div>
                </div>
            </div>
        @endif
    </div>

    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Fecha</th>
                                <th>Concepto</th>
                                <th class="text-end">Importe</th>
                                <th class="text-end">Saldo</th>
                                <th class="pe-3"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($movements as $movement)
                                <tr class="{{ $movement->isCancelled() ? 'opacity-50' : '' }}">
                                    <td class="ps-3 text-nowrap">{{ $movement->occurred_on->format('d/m/Y') }}</td>
                                    <td>
                                        <div class="{{ $movement->isCancelled() ? 'text-decoration-line-through' : '' }}">{{ $movement->concept }}</div>
                                        <small class="text-muted">
                                            {{ $movement->originLabel() }}
                                            @if($movement->reference) · {{ $movement->reference }} @endif
                                            @if($movement->creator) · {{ $movement->creator->name }} @endif
                                        </small>
                                    </td>
                                    <td class="text-end fw-semibold text-nowrap {{ $movement->isIncome() ? 'text-success' : 'text-danger' }}">
                                        {{ $movement->isIncome() ? '+' : '−' }}{{ \App\Support\Money::format($movement->amount, $tenantCompany) }}
                                    </td>
                                    <td class="text-end text-muted text-nowrap">{{ \App\Support\Money::format($movement->balance_after, $tenantCompany) }}</td>
                                    <td class="pe-3 text-end">
                                        @if($canCancel && $movement->isManual() && ! $movement->isCancelled())
                                            <form action="{{ route('accounts.movements.cancel', $movement) }}" method="POST"
                                                  onsubmit="return confirm('¿Anular este movimiento? Se escribirá el contrario.')">
                                                @csrf
                                                @method('PATCH')
                                                <button class="btn btn-sm btn-link text-danger p-0" title="Anular"><i class="bi bi-x-circle"></i></button>
                                            </form>
                                        @elseif($movement->isCancelled())
                                            <span class="badge bg-secondary">Anulado</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted py-5">Todavía no hay movimientos.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="d-flex justify-content-center mt-3">{{ $movements->links() }}</div>
    </div>
</div>
@endsection
