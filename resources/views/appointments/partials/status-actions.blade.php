{{-- Botones de cambio de estado: la acción más frecuente del día a día. --}}
@php
    $next = match ($appointment->status) {
        'reservada' => ['confirmada' => ['Confirmar', 'bi-check-lg', 'primary']],
        'confirmada' => ['atendida' => ['Marcar atendida', 'bi-check2-all', 'success']],
        default => [],
    };
@endphp

@foreach($next as $status => [$label, $icon, $color])
    <form action="{{ route('appointments.status', $appointment) }}" method="POST" class="d-inline m-0">
        @csrf
        @method('PATCH')
        <input type="hidden" name="status" value="{{ $status }}">
        <button class="btn btn-sm btn-outline-{{ $color }}" title="{{ $label }}">
            <i class="bi {{ $icon }}"></i>
        </button>
    </form>
@endforeach

@unless($appointment->isReleased() || $appointment->status === 'atendida')
    <div class="dropdown d-inline">
        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
            <i class="bi bi-three-dots"></i>
        </button>
        <ul class="dropdown-menu dropdown-menu-end shadow border-0">
            <li>
                <form action="{{ route('appointments.status', $appointment) }}" method="POST" class="m-0">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="status" value="no_show">
                    <button class="dropdown-item"><i class="bi bi-person-x"></i> No se presentó</button>
                </form>
            </li>
            <li>
                <form action="{{ route('appointments.status', $appointment) }}" method="POST" class="m-0"
                      onsubmit="return confirm('¿Cancelar la cita de {{ $appointment->client?->full_name }}?')">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="status" value="cancelada">
                    <button class="dropdown-item text-danger"><i class="bi bi-x-circle"></i> Cancelar</button>
                </form>
            </li>
        </ul>
    </div>
@endunless
