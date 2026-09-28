@extends('layouts.app')

@section('title', 'Horario de '.$personal->full_name.' - Barbora')

@section('page')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div class="d-flex align-items-center gap-3">
        @if($personal->photoUrl())
            <img src="{{ $personal->photoUrl() }}" alt="" class="staff-avatar staff-avatar-lg">
        @else
            <span class="staff-avatar staff-avatar-lg staff-avatar-initials"
                  style="background: {{ $personal->agenda_color }};">{{ $personal->initials() }}</span>
        @endif
        <div>
            <h1 class="h4 fw-bold mb-1">{{ $personal->full_name }}</h1>
            <p class="text-muted mb-0">{{ $personal->specialty ?: $personal->cargo?->name }}</p>
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('personal.edit', $personal) }}" class="btn btn-light border">
            <i class="bi bi-pencil"></i> Editar ficha
        </a>
        <a href="{{ route('schedules.index') }}" class="btn btn-outline-secondary">Volver</a>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="fw-bold mb-3">Horario semanal</h6>

                @forelse($schedules->groupBy('weekday') as $weekday => $group)
                    <div class="d-flex align-items-start gap-3 py-2 border-bottom">
                        <div class="fw-semibold" style="min-width: 6rem;">{{ $weekdays[$weekday] }}</div>
                        <div class="flex-fill d-flex flex-wrap gap-2">
                            @foreach($group as $schedule)
                                <span class="schedule-chip d-inline-flex align-items-center gap-2">
                                    {{ $schedule->rangeLabel() }}
                                    <small class="text-muted">{{ $schedule->branchLabel() }}</small>
                                    <form action="{{ route('schedules.destroy', [$personal, $schedule]) }}"
                                          method="POST" class="d-inline m-0"
                                          onsubmit="return confirm('¿Quitar la franja del {{ strtolower($weekdays[$weekday]) }} de {{ $schedule->rangeLabel() }}?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-link btn-sm p-0 text-danger" title="Quitar">
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                    </form>
                                </span>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <p class="text-muted mb-0">
                        Sin horario todavía. Añade las franjas en el formulario de al lado.
                    </p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="fw-bold mb-1">Añadir franja</h6>
                <p class="text-muted small mb-3">
                    Marca varios días para repetir la misma franja («de lunes a viernes, de 9 a 13»).
                    Un turno partido son dos franjas del mismo día.
                </p>

                <form action="{{ route('schedules.store', $personal) }}" method="POST">
                    @csrf

                    <label class="form-label">Días</label>
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        @foreach($weekdays as $number => $label)
                            <div class="form-check form-check-inline m-0">
                                <input class="form-check-input" type="checkbox" name="weekdays[]"
                                       value="{{ $number }}" id="weekday-{{ $number }}"
                                       {{ in_array($number, (array) old('weekdays', []), false) ? 'checked' : '' }}>
                                <label class="form-check-label small" for="weekday-{{ $number }}">
                                    {{ mb_substr($label, 0, 3) }}
                                </label>
                            </div>
                        @endforeach
                    </div>
                    @error('weekdays')<div class="text-danger small mb-2">{{ $message }}</div>@enderror

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label for="start_time" class="form-label">Entrada</label>
                            <input type="time" id="start_time" name="start_time"
                                   class="form-control @error('start_time') is-invalid @enderror"
                                   value="{{ old('start_time', '09:00') }}" required>
                            @error('start_time')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-6">
                            <label for="end_time" class="form-label">Salida</label>
                            <input type="time" id="end_time" name="end_time"
                                   class="form-control @error('end_time') is-invalid @enderror"
                                   value="{{ old('end_time', '13:00') }}" required>
                            @error('end_time')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="branch_id" class="form-label">Sucursal</label>
                        <select id="branch_id" name="branch_id" class="form-select">
                            <option value="">Cualquier sucursal</option>
                            @foreach($branches as $branch)
                                <option value="{{ $branch->id }}" {{ (string) old('branch_id') === (string) $branch->id ? 'selected' : '' }}>
                                    {{ $branch->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('branch_id')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>

                    <button class="btn btn-primary w-100">
                        <i class="bi bi-plus-circle"></i> Añadir al horario
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
