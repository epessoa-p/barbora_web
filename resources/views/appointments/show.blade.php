@extends('layouts.app')

@section('title', 'Cita - Barbora')

@section('page')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h4 fw-bold mb-1">
            {{ $appointment->client?->full_name }}
            <span class="badge bg-{{ $appointment->statusColor() }} align-middle ms-1">
                {{ $appointment->statusLabel() }}
            </span>
        </h1>
        <p class="text-muted mb-0">
            {{ $appointment->starts_at->translatedFormat('l d \d\e F \d\e Y') }} ·
            {{ $appointment->rangeLabel() }} ({{ $appointment->durationMinutes() }} min)
        </p>
    </div>
    <div class="d-flex gap-2">
        {{-- Cobrar cierra el ciclo: la venta marca la cita como atendida. --}}
        @if(! $appointment->isReleased() && $appointment->status !== 'atendida'
            && auth()->user()->hasPermissionInCompany('sales.create', $tenantCompany)
            && $tenantCompany?->planAllows('pos'))
            <a href="{{ route('sales.create', ['appointment' => $appointment->id]) }}" class="btn btn-success">
                <i class="bi bi-cash-coin"></i> Cobrar
            </a>
        @endif
        @if($appointment->isEditable())
            <a href="{{ route('appointments.edit', $appointment) }}" class="btn btn-primary">
                <i class="bi bi-pencil"></i> Editar
            </a>
        @endif
        <a href="{{ route('appointments.index', ['day' => $appointment->starts_at->toDateString()]) }}"
           class="btn btn-outline-secondary">Volver al día</a>
    </div>
</div>

@if($appointment->client?->allergies)
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <strong>Alergias del cliente:</strong> {{ $appointment->client->allergies }}
    </div>
@endif

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="fw-bold mb-3">Servicios</h6>
                <table class="table mb-0">
                    <tbody>
                        @foreach($appointment->services as $service)
                            <tr>
                                <td class="ps-0">
                                    {{ $service->name }}
                                    <small class="text-muted d-block">{{ $service->pivot->duration_minutes }} min</small>
                                </td>
                                <td class="text-end pe-0 fw-semibold">
                                    {{ \App\Support\Money::format($service->pivot->price, $tenantCompany) }}
                                </td>
                            </tr>
                        @endforeach
                        <tr class="border-top">
                            <td class="ps-0 fw-bold">Total</td>
                            <td class="text-end pe-0 fw-bold fs-5">
                                {{ \App\Support\Money::format($appointment->total(), $tenantCompany) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p class="text-muted small mb-0 mt-2">
                    Precios congelados al reservar: si la tarifa cambia, esta cita mantiene lo pactado.
                </p>
            </div>
        </div>

        @if($appointment->notes || $appointment->cancel_reason)
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-body">
                    <h6 class="fw-bold mb-3">Notas</h6>
                    @if($appointment->notes)<p class="mb-1">{{ $appointment->notes }}</p>@endif
                    @if($appointment->cancel_reason)
                        <p class="text-danger mb-0"><strong>Motivo de cancelación:</strong> {{ $appointment->cancel_reason }}</p>
                    @endif
                </div>
            </div>
        @endif
    </div>

    <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="fw-bold mb-3">Detalle</h6>
                <p class="mb-1">
                    <strong>Barbero:</strong>
                    <span class="d-inline-block rounded-circle align-middle"
                          style="width:.7rem;height:.7rem;background:{{ $appointment->personal?->agenda_color }};"></span>
                    {{ $appointment->personal?->full_name }}
                </p>
                <p class="mb-1"><strong>Sucursal:</strong> {{ $appointment->branch?->name ?? 'Sin especificar' }}</p>
                <p class="mb-1"><strong>Teléfono:</strong> {{ $appointment->client?->phone ?: '—' }}</p>
                <p class="mb-0"><strong>Reservada por:</strong> {{ $appointment->creator?->name ?? '—' }}</p>
            </div>
        </div>

        <div class="card border-0 shadow-sm mt-4">
            <div class="card-body">
                <h6 class="fw-bold mb-3">Estado</h6>

                <div class="d-flex flex-wrap gap-2">
                    @foreach(\App\Models\Appointment::STATUSES as $value => $label)
                        @continue($value === $appointment->status)
                        <form action="{{ route('appointments.status', $appointment) }}" method="POST" class="m-0"
                              @if($value === 'cancelada') onsubmit="return confirm('¿Cancelar esta cita?')" @endif>
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="{{ $value }}">
                            <button class="btn btn-sm btn-outline-secondary">{{ $label }}</button>
                        </form>
                    @endforeach
                </div>

                <form action="{{ route('appointments.destroy', $appointment) }}" method="POST" class="mt-3"
                      onsubmit="return confirm('¿Eliminar la cita de la agenda? Para dejar constancia, mejor cancélala.')">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-sm btn-outline-danger w-100">
                        <i class="bi bi-trash"></i> Eliminar de la agenda
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
