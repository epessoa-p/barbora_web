@php
    $allDay = old('all_day', $block?->all_day ?? true);
@endphp

<div class="container-fluid" style="max-width: 760px;">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="h4 fw-bold mb-1">{{ $block ? 'Editar bloqueo' : 'Nuevo bloqueo' }}</h1>
            <p class="text-muted mb-0">Un periodo en el que no se puede reservar.</p>
        </div>
        <a href="{{ route('agenda-blocks.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0 ps-3">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form action="{{ $action }}" method="POST">
        @csrf
        @if($method !== 'POST') @method($method) @endif

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-md-7">
                        <label class="form-label">Título <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control"
                               value="{{ old('title', $block?->title) }}"
                               placeholder="Ej: Vacaciones de Tania, Feriado 6 de agosto" required>
                    </div>

                    <div class="col-md-5">
                        <label class="form-label">Motivo <span class="text-danger">*</span></label>
                        <select name="reason" class="form-select" required>
                            @foreach(\App\Models\AgendaBlock::REASONS as $value => $label)
                                <option value="{{ $value }}" {{ old('reason', $block?->reason) === $value ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">¿A quién afecta?</label>
                        <select name="personal_id" class="form-select">
                            <option value="">Toda la barbería (feriado)</option>
                            @foreach($staff as $person)
                                <option value="{{ $person->id }}"
                                    {{ (string) old('personal_id', $block?->personal_id) === (string) $person->id ? 'selected' : '' }}>
                                    {{ $person->full_name }}
                                </option>
                            @endforeach
                        </select>
                        <small class="text-muted">Sin elegir barbero, cierra la agenda de todos.</small>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Sucursal</label>
                        <select name="branch_id" class="form-select">
                            <option value="">Todas</option>
                            @foreach($branches as $branch)
                                <option value="{{ $branch->id }}"
                                    {{ (string) old('branch_id', $block?->branch_id) === (string) $branch->id ? 'selected' : '' }}>
                                    {{ $branch->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-3"><i class="bi bi-calendar-range"></i> Periodo</h6>

                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" name="all_day" id="all_day" value="1"
                           {{ $allDay ? 'checked' : '' }}>
                    <label class="form-check-label" for="all_day">Día(s) completo(s)</label>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Desde <span class="text-danger">*</span></label>
                        <input type="date" name="start_date" class="form-control" required
                               value="{{ old('start_date', ($block?->starts_at ?? $defaults['starts_at'] ?? now())->toDateString()) }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Hasta <span class="text-danger">*</span></label>
                        <input type="date" name="end_date" class="form-control" required
                               value="{{ old('end_date', ($block?->ends_at ?? $defaults['starts_at'] ?? now())->toDateString()) }}">
                    </div>

                    <div class="col-md-6 time-field {{ $allDay ? 'd-none' : '' }}">
                        <label class="form-label">Hora de inicio</label>
                        <input type="time" name="start_time" class="form-control"
                               value="{{ old('start_time', $block && ! $block->all_day ? $block->starts_at->format('H:i') : '13:00') }}">
                    </div>
                    <div class="col-md-6 time-field {{ $allDay ? 'd-none' : '' }}">
                        <label class="form-label">Hora de fin</label>
                        <input type="time" name="end_time" class="form-control"
                               value="{{ old('end_time', $block && ! $block->all_day ? $block->ends_at->format('H:i') : '14:00') }}">
                    </div>

                    <div class="col-12">
                        <label class="form-label">Notas</label>
                        <textarea name="notes" rows="2" class="form-control">{{ old('notes', $block?->notes) }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button class="btn btn-primary px-4" type="submit">
                <i class="bi bi-save"></i> {{ $block ? 'Guardar cambios' : 'Crear bloqueo' }}
            </button>
            <a href="{{ route('agenda-blocks.index') }}" class="btn btn-light border">Cancelar</a>
        </div>
    </form>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const allDay = document.getElementById('all_day');
    const fields = Array.from(document.querySelectorAll('.time-field'));
    if (!allDay) return;

    // Un bloqueo de día completo no pide horas: se guarda de 00:00 a 23:59.
    function toggle() {
        fields.forEach((field) => field.classList.toggle('d-none', allDay.checked));
    }

    allDay.addEventListener('change', toggle);
    toggle();
});
</script>
@endpush
