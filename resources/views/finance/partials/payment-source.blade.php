{{--
    ¿De dónde sale el dinero? Dos pestañas: una cuenta (capital, caja chica,
    banco…) o un turno de caja abierto. Lo comparten Gastos y Compras.

    Variables: $sources (['account', 'cash'] según el plan), $accounts,
    $openSessions, $preferredBranchId. Opcional: $title.
--}}
@php
    $hasAccounts = in_array('account', $sources, true);
    $hasCash = in_array('cash', $sources, true);
    $current = old('source', $hasAccounts ? 'account' : 'cash');
    $preferredSession = $openSessions->firstWhere('branch_id', $preferredBranchId) ?? $openSessions->first();
@endphp

<div class="payment-source">
    <label class="form-label fw-semibold">{{ $title ?? '¿De dónde sale el dinero?' }}</label>

    @if(! $hasAccounts && ! $hasCash)
        <div class="alert alert-warning small py-2 mb-0">Tu plan no incluye cuentas ni caja: no se puede pagar desde aquí.</div>
    @else
        <ul class="nav nav-tabs mb-3" role="tablist">
            @if($hasAccounts)
                <li class="nav-item">
                    <button type="button" class="nav-link {{ $current === 'account' ? 'active' : '' }}" data-bs-toggle="tab"
                            data-bs-target="#source-account" data-source="account">
                        <i class="bi bi-bank"></i> Cuenta
                    </button>
                </li>
            @endif
            @if($hasCash)
                <li class="nav-item">
                    <button type="button" class="nav-link {{ $current === 'cash' ? 'active' : '' }}" data-bs-toggle="tab"
                            data-bs-target="#source-cash" data-source="cash">
                        <i class="bi bi-cash-coin"></i> Caja abierta
                    </button>
                </li>
            @endif
        </ul>
        <input type="hidden" name="source" value="{{ $current }}" class="source-input">
        @error('source')<div class="text-danger small mb-2">{{ $message }}</div>@enderror

        <div class="tab-content">
            @if($hasAccounts)
                <div class="tab-pane fade {{ $current === 'account' ? 'show active' : '' }}" id="source-account">
                    @if($accounts->isEmpty())
                        <div class="alert alert-info small py-2 mb-0">
                            No hay cuentas activas.
                            @if(auth()->user()->hasPermissionInCompany('accounts.create', $tenantCompany))
                                <a href="{{ route('accounts.create') }}">Crea una</a> (capital, caja chica, banco…).
                            @endif
                        </div>
                    @else
                        <select name="finance_account_id" class="form-select @error('finance_account_id') is-invalid @enderror">
                            @foreach($accounts as $account)
                                <option value="{{ $account->id }}" {{ (string) old('finance_account_id') === (string) $account->id ? 'selected' : '' }}>
                                    {{ $account->name }} · {{ $account->typeLabel() }} — saldo {{ \App\Support\Money::format($account->balance, $tenantCompany) }}
                                </option>
                            @endforeach
                        </select>
                        @error('finance_account_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    @endif
                </div>
            @endif

            @if($hasCash)
                <div class="tab-pane fade {{ $current === 'cash' ? 'show active' : '' }}" id="source-cash">
                    @if($openSessions->isEmpty())
                        <div class="alert alert-warning small py-2 mb-0">
                            No hay ningún turno de caja abierto.
                            <a href="{{ route('cash.current') }}">Abre la caja</a> para pagar desde ella.
                        </div>
                    @else
                        <select name="cash_session_id" class="form-select @error('cash_session_id') is-invalid @enderror">
                            @foreach($openSessions as $session)
                                <option value="{{ $session->id }}"
                                    {{ (string) old('cash_session_id', $preferredSession?->id) === (string) $session->id ? 'selected' : '' }}>
                                    {{ $session->caja?->name }} · {{ $session->number }}
                                    — esperado {{ \App\Support\Money::format($session->expectedCash(), $tenantCompany) }}
                                </option>
                            @endforeach
                        </select>
                        <small class="text-muted">Sale en efectivo del cajón: se descuenta en el arqueo del turno.</small>
                        @error('cash_session_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    @endif
                </div>
            @endif
        </div>
    @endif
</div>

@once
    @push('scripts')
    <script>
    // La pestaña elegida es el origen del dinero que se envía.
    document.querySelectorAll('.payment-source [data-source]').forEach(function (tab) {
        tab.addEventListener('shown.bs.tab', function () {
            tab.closest('.payment-source').querySelector('.source-input').value = tab.dataset.source;
        });
    });
    </script>
    @endpush
@endonce
