@php
    $start = $appointment?->starts_at ?? ($defaults['starts_at'] ?? now());
    $selectedServices = old('services', $appointment?->services->pluck('id')->all() ?? []);
    $selectedPersonal = old('personal_id', $appointment?->personal_id ?? ($defaults['personal_id'] ?? null));
@endphp

<form action="{{ $appointment ? route('appointments.update', $appointment) : route('appointments.store') }}"
      method="POST" class="row g-4">
    @csrf
    @if($appointment) @method('PUT') @endif

    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-3"><i class="bi bi-calendar-plus"></i> Cuándo y con quién</h6>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="date" class="form-label">Fecha</label>
                        <input type="date" id="date" name="date" class="form-control @error('date') is-invalid @enderror"
                               value="{{ old('date', $start->toDateString()) }}" required>
                        @error('date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label for="time" class="form-label">Hora de inicio</label>
                        <input type="time" id="time" name="time" class="form-control @error('time') is-invalid @enderror"
                               value="{{ old('time', $start->format('H:i')) }}" step="300" required>
                        @error('time')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <small class="text-muted">La hora de fin se calcula sola con la duración de los servicios.</small>
                    </div>

                    <div class="col-md-6">
                        <label for="personal_id" class="form-label">Barbero</label>
                        <select id="personal_id" name="personal_id"
                                class="form-select @error('personal_id') is-invalid @enderror" required>
                            <option value="">Elegir barbero</option>
                            @foreach($staff as $person)
                                <option value="{{ $person->id }}" {{ (string) $selectedPersonal === (string) $person->id ? 'selected' : '' }}>
                                    {{ $person->full_name }}{{ $person->specialty ? ' · '.$person->specialty : '' }}
                                </option>
                            @endforeach
                        </select>
                        @error('personal_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label for="branch_id" class="form-label">Sucursal</label>
                        <select id="branch_id" name="branch_id" class="form-select @error('branch_id') is-invalid @enderror">
                            <option value="">Sin especificar</option>
                            @foreach($branches as $branch)
                                <option value="{{ $branch->id }}" {{ (string) old('branch_id', $appointment?->branch_id) === (string) $branch->id ? 'selected' : '' }}>
                                    {{ $branch->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('branch_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12">
                        <label for="client_id" class="form-label">Cliente</label>
                        <select id="client_id" name="client_id"
                                class="form-select @error('client_id') is-invalid @enderror" required>
                            <option value="">Elegir cliente</option>
                            @foreach($clients as $client)
                                <option value="{{ $client->id }}" {{ (string) old('client_id', $appointment?->client_id) === (string) $client->id ? 'selected' : '' }}>
                                    {{ $client->full_name }}{{ $client->phone ? ' · '.$client->phone : '' }}
                                </option>
                            @endforeach
                        </select>
                        @error('client_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <small class="text-muted">
                            ¿No está en la lista? <a href="{{ route('clients.create') }}" target="_blank">Regístralo primero</a>.
                        </small>
                    </div>

                    @if($appointment)
                        <div class="col-md-6">
                            <label for="status" class="form-label">Estado</label>
                            <select id="status" name="status" class="form-select">
                                @foreach(\App\Models\Appointment::STATUSES as $value => $label)
                                    <option value="{{ $value }}" {{ old('status', $appointment->status) === $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div class="col-12">
                        <label for="notes" class="form-label">Notas</label>
                        <textarea id="notes" name="notes" rows="2" class="form-control">{{ old('notes', $appointment?->notes) }}</textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-1"><i class="bi bi-scissors"></i> Servicios</h6>
                <p class="text-muted small mb-3">
                    Marca todo lo que se hará. La suma de duraciones es el hueco que se reserva.
                </p>

                @error('services')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror

                @forelse($services->groupBy(fn ($s) => $s->category?->name ?? 'Sin categoría') as $categoryName => $group)
                    <div class="mb-3">
                        <div class="text-muted small fw-semibold text-uppercase mb-1">{{ $categoryName }}</div>
                        @foreach($group as $service)
                            <div class="form-check d-flex justify-content-between align-items-center py-1">
                                <span>
                                    <input class="form-check-input service-check" type="checkbox" name="services[]"
                                           value="{{ $service->id }}" id="service-{{ $service->id }}"
                                           data-minutes="{{ $service->duration_minutes }}"
                                           data-price="{{ $service->price }}"
                                           {{ in_array($service->id, (array) $selectedServices) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="service-{{ $service->id }}">
                                        {{ $service->name }}
                                        <small class="text-muted d-block">{{ $service->durationLabel() }}</small>
                                    </label>
                                </span>
                                <span class="fw-semibold text-nowrap">
                                    {{ \App\Support\Money::format($service->price, $tenantCompany) }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                @empty
                    <div class="alert alert-warning py-2 mb-0">
                        No hay servicios activos. <a href="{{ route('services.create') }}">Crea el primero</a>.
                    </div>
                @endforelse

                <hr>

                <div class="d-flex justify-content-between">
                    <span class="text-muted">Duración</span>
                    <span class="fw-semibold" id="total-duration">0 min</span>
                </div>
                <div class="d-flex justify-content-between">
                    <span class="text-muted">Total</span>
                    <span class="fw-bold fs-5" id="total-price">
                        {{ \App\Support\Money::format(0, $tenantCompany) }}
                    </span>
                </div>
            </div>
        </div>

        <div class="d-grid gap-2 mt-4">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-check-circle"></i> {{ $appointment ? 'Guardar cambios' : 'Reservar cita' }}
            </button>
            <a href="{{ route('appointments.index') }}" class="btn btn-light border">Cancelar</a>
        </div>
    </div>
</form>

@push('scripts')
<script>
(function () {
    // Totales en vivo: duración y precio se leen de los servicios marcados.
    const checks = Array.from(document.querySelectorAll('.service-check'));
    const durationEl = document.getElementById('total-duration');
    const priceEl = document.getElementById('total-price');
    const symbol = @json(\App\Support\Money::symbol($tenantCompany));

    function refresh() {
        let minutes = 0;
        let price = 0;

        checks.filter((c) => c.checked).forEach(function (c) {
            minutes += parseInt(c.dataset.minutes, 10) || 0;
            price += parseFloat(c.dataset.price) || 0;
        });

        durationEl.textContent = minutes < 60
            ? minutes + ' min'
            : Math.floor(minutes / 60) + ' h' + (minutes % 60 ? ' ' + (minutes % 60) + ' min' : '');

        priceEl.textContent = symbol + ' ' + price.toFixed(2);
    }

    checks.forEach((c) => c.addEventListener('change', refresh));
    refresh();
})();
</script>
@endpush
