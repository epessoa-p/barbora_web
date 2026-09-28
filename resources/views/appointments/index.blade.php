@extends('layouts.app')

@section('title', 'Citas del día - Barbora')

@section('page')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h4 fw-bold mb-1"><i class="bi bi-calendar-check"></i> Citas del día</h1>
        <p class="text-muted mb-0">{{ $day->translatedFormat('l d \d\e F \d\e Y') }}</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('appointments.calendar', ['day' => $day->toDateString()]) }}" class="btn btn-light border">
            <i class="bi bi-calendar3"></i> Calendario
        </a>
        <a href="{{ route('appointments.create', ['day' => $day->toDateString()]) }}" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Nueva cita
        </a>
    </div>
</div>

@include('appointments.partials.day-nav', ['day' => $day, 'route' => 'appointments.index'])

<div class="row g-3 mb-4">
    @foreach([
        ['Citas', $summary['total'], 'bi-calendar-check', '#2563eb'],
        ['Pendientes', $summary['pending'], 'bi-hourglass-split', '#f59e0b'],
        ['Atendidas', $summary['done'], 'bi-check2-circle', '#16a34a'],
        ['Ocupación', intdiv($summary['minutes'], 60).' h '.($summary['minutes'] % 60).' min', 'bi-clock', '#0d9488'],
    ] as [$label, $value, $icon, $color])
        <div class="col-xl-3 col-md-6">
            <div class="kpi-card">
                <div class="kpi-body">
                    <div>
                        <div class="kpi-value">{{ $value }}</div>
                        <div class="kpi-label">{{ $label }}</div>
                    </div>
                    <div class="kpi-icon" style="background: {{ $color }};"><i class="bi {{ $icon }}"></i></div>
                </div>
            </div>
        </div>
    @endforeach
</div>

<form method="GET" class="row g-2 mb-3">
    <input type="hidden" name="day" value="{{ $day->toDateString() }}">
    <div class="col-md-4">
        <select name="personal" class="form-select">
            <option value="">Todos los barberos</option>
            @foreach($staff as $person)
                <option value="{{ $person->id }}" {{ (string) request('personal') === (string) $person->id ? 'selected' : '' }}>
                    {{ $person->full_name }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4">
        <select name="status" class="form-select">
            <option value="">Todos los estados</option>
            @foreach(\App\Models\Appointment::STATUSES as $value => $label)
                <option value="{{ $value }}" {{ request('status') === $value ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4 d-flex gap-2">
        <button class="btn btn-outline-secondary"><i class="bi bi-funnel"></i> Filtrar</button>
        @if(request('personal') || request('status'))
            <a href="{{ route('appointments.index', ['day' => $day->toDateString()]) }}" class="btn btn-light border">Limpiar</a>
        @endif
    </div>
</form>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Hora</th>
                        <th>Cliente</th>
                        <th>Servicios</th>
                        <th>Barbero</th>
                        <th class="text-end">Total</th>
                        <th class="text-center">Estado</th>
                        <th class="text-end pe-3">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($appointments as $appointment)
                        <tr class="{{ $appointment->isReleased() ? 'opacity-50' : '' }}">
                            <td class="ps-3 text-nowrap fw-semibold">{{ $appointment->rangeLabel() }}</td>
                            <td>
                                <a href="{{ route('clients.show', $appointment->client) }}" class="text-decoration-none">
                                    {{ $appointment->client?->full_name }}
                                </a>
                                @if($appointment->client?->allergies)
                                    <i class="bi bi-exclamation-triangle-fill text-warning ms-1"
                                       title="Alergias: {{ $appointment->client->allergies }}"></i>
                                @endif
                            </td>
                            <td>
                                <span class="d-block">{{ $appointment->servicesLabel() }}</span>
                                <small class="text-muted">{{ $appointment->durationMinutes() }} min</small>
                            </td>
                            <td>
                                <span class="d-inline-flex align-items-center gap-2">
                                    <span class="d-inline-block rounded-circle"
                                          style="width:.7rem;height:.7rem;background:{{ $appointment->personal?->agenda_color }};"></span>
                                    {{ $appointment->personal?->full_name }}
                                </span>
                            </td>
                            <td class="text-end fw-semibold">
                                {{ \App\Support\Money::format($appointment->total(), $tenantCompany) }}
                            </td>
                            <td class="text-center">
                                <span class="badge bg-{{ $appointment->statusColor() }}">{{ $appointment->statusLabel() }}</span>
                            </td>
                            <td class="text-end pe-3 text-nowrap">
                                @include('appointments.partials.status-actions', ['appointment' => $appointment])
                                <a href="{{ route('appointments.show', $appointment) }}" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                No hay citas para este día.
                                <a href="{{ route('appointments.create', ['day' => $day->toDateString()]) }}">Reserva la primera</a>.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
