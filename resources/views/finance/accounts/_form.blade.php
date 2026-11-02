@if($errors->any())
    <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif

<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <div class="row g-3">
            <div class="col-md-7">
                <label class="form-label">Nombre <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control" maxlength="255" required
                       value="{{ old('name', $account?->name) }}" placeholder="CAJA CHICA, CAPITAL, BANCO UNIÓN…">
            </div>
            <div class="col-md-5">
                <label class="form-label">Tipo <span class="text-danger">*</span></label>
                <select name="type" class="form-select" required>
                    @foreach(\App\Models\FinanceAccount::TYPES as $value => $label)
                        <option value="{{ $value }}" {{ old('type', $account?->type ?? 'caja_chica') === $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Banco o billetera</label>
                <input type="text" name="bank_name" class="form-control" data-no-upper value="{{ old('bank_name', $account?->bank_name) }}" placeholder="Opcional">
            </div>
            <div class="col-md-6">
                <label class="form-label">Nº de cuenta</label>
                <input type="text" name="account_number" class="form-control" value="{{ old('account_number', $account?->account_number) }}" placeholder="Opcional">
            </div>
            @include('partials.branch-select', [
                'branches' => $branches,
                'selected' => $account?->branch_id,
                'placeholder' => 'Toda la barbería',
                'emptyWhenSingle' => true,
                'help' => 'Opcional: por ejemplo, la caja chica de una sucursal.',
            ])
            @unless($account)
                <div class="col-md-6">
                    <label class="form-label">Saldo inicial</label>
                    <div class="input-group">
                        <span class="input-group-text">{{ \App\Support\Money::symbol($tenantCompany) }}</span>
                        <input type="number" step="0.01" min="0" name="opening_balance" class="form-control" value="{{ old('opening_balance', 0) }}">
                    </div>
                    <small class="text-muted">Lo que hay hoy en la cuenta. Entra como primer movimiento.</small>
                </div>
            @endunless
            <div class="col-12">
                <label class="form-label">Notas</label>
                <textarea name="notes" rows="2" class="form-control">{{ old('notes', $account?->notes) }}</textarea>
            </div>
            @if($account)
                <div class="col-12">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="active" value="1" id="active" {{ old('active', $account->active) ? 'checked' : '' }}>
                        <label class="form-check-label" for="active">Activa</label>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
