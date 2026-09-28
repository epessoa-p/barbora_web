<div class="card mt-4">
    <div class="card-body">
        <form action="{{ $rule ? route('commission-rules.update', $rule) : route('commission-rules.store') }}" method="POST">
            @csrf
            @if($rule) @method('PUT') @endif

            <div class="row g-3">
                <div class="col-md-6">
                    <label for="personal_id" class="form-label">Se aplica a</label>
                    <select id="personal_id" name="personal_id" class="form-select">
                        <option value="">Todo el equipo</option>
                        @foreach($staff as $person)
                            <option value="{{ $person->id }}"
                                    {{ (string) old('personal_id', $rule?->personal_id) === (string) $person->id ? 'selected' : '' }}>
                                {{ $person->full_name }}
                            </option>
                        @endforeach
                    </select>
                    <small class="text-muted">Una regla para alguien concreto gana a la general.</small>
                </div>

                <div class="col-md-6">
                    <label for="applies_to" class="form-label">Sobre</label>
                    <select id="applies_to" name="applies_to" class="form-select" required>
                        @foreach(\App\Models\CommissionRule::APPLIES_TO as $value => $label)
                            <option value="{{ $value }}" {{ old('applies_to', $rule?->applies_to) === $value ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-12 {{ old('applies_to', $rule?->applies_to) === 'servicio' ? '' : 'd-none' }}" id="service-row">
                    <label for="service_id" class="form-label">Servicio</label>
                    <select id="service_id" name="service_id" class="form-select @error('service_id') is-invalid @enderror">
                        <option value="">Elegir servicio</option>
                        @foreach($services as $service)
                            <option value="{{ $service->id }}"
                                    {{ (string) old('service_id', $rule?->service_id) === (string) $service->id ? 'selected' : '' }}>
                                {{ $service->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('service_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-5">
                    <label for="type" class="form-label">Tipo</label>
                    <select id="type" name="type" class="form-select" required>
                        @foreach(\App\Models\CommissionRule::TYPES as $value => $label)
                            <option value="{{ $value }}" {{ old('type', $rule?->type ?? 'porcentaje') === $value ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-4">
                    <label for="value" class="form-label">Valor</label>
                    <div class="input-group">
                        <span class="input-group-text" id="value-prefix">%</span>
                        <input type="number" step="0.01" min="0" id="value" name="value"
                               class="form-control @error('value') is-invalid @enderror"
                               value="{{ old('value', $rule?->value ?? 40) }}" required>
                        @error('value')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <small class="text-muted" id="value-hint">Porcentaje sobre el importe de la línea.</small>
                </div>

                <div class="col-md-3 d-flex align-items-end">
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" id="active" name="active" value="1"
                               {{ old('active', $rule?->active ?? true) ? 'checked' : '' }}>
                        <label class="form-check-label" for="active">Regla activa</label>
                    </div>
                </div>

                <div class="col-12">
                    <label for="notes" class="form-label">Notas</label>
                    <input type="text" id="notes" name="notes" class="form-control"
                           value="{{ old('notes', $rule?->notes) }}" placeholder="Acordado en la revisión de marzo…">
                </div>

                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle"></i> {{ $rule ? 'Actualizar' : 'Crear regla' }}
                    </button>
                    <a href="{{ route('commission-rules.index') }}" class="btn btn-light border">
                        <i class="bi bi-arrow-left"></i> Cancelar
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
(function () {
    const appliesTo = document.getElementById('applies_to');
    const serviceRow = document.getElementById('service-row');
    const type = document.getElementById('type');
    const prefix = document.getElementById('value-prefix');
    const hint = document.getElementById('value-hint');
    const symbol = @json(\App\Support\Money::symbol($tenantCompany));

    // El servicio solo se pide cuando la regla es de ese alcance.
    appliesTo.addEventListener('change', function () {
        serviceRow.classList.toggle('d-none', this.value !== 'servicio');
    });

    type.addEventListener('change', function () {
        const isPercent = this.value === 'porcentaje';
        prefix.textContent = isPercent ? '%' : symbol;
        hint.textContent = isPercent
            ? 'Porcentaje sobre el importe de la línea.'
            : 'Importe fijo por cada línea que encaje.';
    });
})();
</script>
@endpush
