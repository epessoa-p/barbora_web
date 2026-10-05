@php
    $countries = config('barbora.countries');
    $currencies = config('barbora.currencies');
    $selectedCountry = old('country', $company?->country ?? config('barbora.default_country'));
    $selectedCurrency = old('currency', $company?->currency ?? config('barbora.default_currency'));
    $selectedTimezone = old('timezone', $company?->timezone ?? config('barbora.default_timezone'));
    $taxLabel = old('tax_id_label', $company?->tax_id_label ?? ($countries[$selectedCountry]['tax_label'] ?? 'NIT'));
@endphp

<div class="card mt-4">
    <div class="card-body">
        <form action="{{ $company ? route('companies.update', $company) : route('companies.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            @if($company)
                @method('PUT')
            @endif

            <div class="row g-3">
                <div class="col-md-6">
                    <label for="name" class="form-label">Nombre</label>
                    <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror"
                           value="{{ old('name', $company?->name) }}" required>
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6">
                    <label for="country" class="form-label">País</label>
                    <select id="country" name="country" class="form-select @error('country') is-invalid @enderror" required>
                        @foreach($countries as $code => $meta)
                            <option value="{{ $code }}"
                                    data-tax-label="{{ $meta['tax_label'] }}"
                                    data-currency="{{ $meta['currency'] }}"
                                    data-timezone="{{ $meta['timezone'] }}"
                                    {{ $selectedCountry === $code ? 'selected' : '' }}>
                                {{ $meta['name'] }}
                            </option>
                        @endforeach
                    </select>
                    @error('country')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6">
                    <label for="tax_id" class="form-label" id="tax-id-label">{{ $taxLabel }}</label>
                    <input type="text" id="tax_id" name="tax_id" class="form-control @error('tax_id') is-invalid @enderror"
                           value="{{ old('tax_id', $company?->tax_id) }}">
                    <input type="hidden" id="tax_id_label" name="tax_id_label" value="{{ $taxLabel }}">
                    @error('tax_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-3">
                    <label for="currency" class="form-label">Moneda</label>
                    <select id="currency" name="currency" class="form-select @error('currency') is-invalid @enderror" required>
                        @foreach($currencies as $code => $meta)
                            <option value="{{ $code }}" {{ $selectedCurrency === $code ? 'selected' : '' }}>
                                {{ $code }} ({{ $meta['symbol'] }})
                            </option>
                        @endforeach
                    </select>
                    @error('currency')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-3">
                    <label for="timezone" class="form-label">Zona horaria</label>
                    <select id="timezone" name="timezone" class="form-select @error('timezone') is-invalid @enderror" required>
                        @foreach(timezone_identifiers_list() as $tz)
                            <option value="{{ $tz }}" {{ $selectedTimezone === $tz ? 'selected' : '' }}>{{ $tz }}</option>
                        @endforeach
                    </select>
                    @error('timezone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" id="email" name="email" class="form-control @error('email') is-invalid @enderror"
                           value="{{ old('email', $company?->email) }}">
                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6">
                    <label for="phone" class="form-label">Teléfono</label>
                    <input type="text" id="phone" name="phone" class="form-control @error('phone') is-invalid @enderror"
                           value="{{ old('phone', $company?->phone) }}">
                    @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12">
                    <label for="address" class="form-label">Dirección</label>
                    <input type="text" id="address" name="address" class="form-control @error('address') is-invalid @enderror"
                           value="{{ old('address', $company?->address) }}">
                    @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12">
                    <label for="description" class="form-label">Descripción</label>
                    <textarea id="description" name="description" rows="3"
                              class="form-control @error('description') is-invalid @enderror">{{ old('description', $company?->description) }}</textarea>
                    @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12">
                    <label for="logo" class="form-label">Logo</label>
                    <div class="d-flex align-items-center gap-3">
                        @if($company?->logoUrl())
                            <img src="{{ $company->logoUrl() }}" alt="Logo de {{ $company->name }}"
                                 style="height:56px;width:56px;object-fit:contain;border:1px solid #dee2e6;border-radius:8px;background:#fff;">
                        @endif
                        <div class="flex-grow-1">
                            <input type="file" id="logo" name="logo" accept="image/png,image/jpeg,image/webp"
                                   class="form-control @error('logo') is-invalid @enderror">
                            <small class="text-muted">PNG, JPG o WEBP, hasta 1 MB.</small>
                            @error('logo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    @if($company?->logoUrl())
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" id="remove_logo" name="remove_logo" value="1">
                            <label class="form-check-label" for="remove_logo">Quitar el logo actual</label>
                        </div>
                    @endif
                </div>

                @if(! $company && isset($plans))
                    <div class="col-md-6">
                        <label for="plan_id" class="form-label">Plan inicial</label>
                        <select id="plan_id" name="plan_id" class="form-select">
                            <option value="">Sin suscripción (la empresa no podrá entrar)</option>
                            @foreach($plans as $plan)
                                <option value="{{ $plan->id }}" {{ (string) old('plan_id') === (string) $plan->id ? 'selected' : '' }}>
                                    {{ $plan->name }} — {{ \App\Support\Money::format($plan->price, $selectedCurrency) }}
                                    ({{ $plan->trial_days }} días de prueba)
                                </option>
                            @endforeach
                        </select>
                        <small class="text-muted">Se crea una suscripción en prueba a partir de hoy.</small>
                    </div>
                @endif

                <div class="col-12">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="active" name="active" value="1"
                               {{ old('active', $company?->active ?? true) ? 'checked' : '' }}>
                        <label class="form-check-label" for="active">Empresa activa</label>
                    </div>
                </div>

                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle"></i> {{ $company ? 'Actualizar' : 'Crear Empresa' }}
                    </button>
                    <a href="{{ route('companies.index') }}" class="btn btn-light border">
                        <i class="bi bi-arrow-left"></i> Cancelar
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    // Al cambiar de país, proponer su identificador fiscal, moneda y zona horaria.
    document.getElementById('country')?.addEventListener('change', function () {
        const option = this.selectedOptions[0];
        if (!option) return;

        document.getElementById('tax-id-label').textContent = option.dataset.taxLabel;
        document.getElementById('tax_id_label').value = option.dataset.taxLabel;
        document.getElementById('currency').value = option.dataset.currency;
        document.getElementById('timezone').value = option.dataset.timezone;
    });
</script>
@endpush
