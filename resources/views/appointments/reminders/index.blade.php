@extends('layouts.app')

@section('title', 'Recordatorios - Barbora')

@section('page')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h4 fw-bold mb-1"><i class="bi bi-bell"></i> Recordatorios</h1>
        <p class="text-muted mb-0">
            Citas del {{ $from->translatedFormat('d/m') }} al {{ $to->translatedFormat('d/m') }}
            que conviene confirmar con el cliente.
        </p>
    </div>
    <form method="GET" class="d-flex align-items-center gap-2">
        <label class="form-label mb-0 small text-muted">Próximos</label>
        <select name="days" class="form-select form-select-sm" onchange="this.form.submit()" style="width: auto;">
            @foreach([0 => 'Hoy', 1 => 'Hasta mañana', 2 => '2 días', 3 => '3 días', 7 => 'Una semana'] as $value => $label)
                <option value="{{ $value }}" {{ $days === $value ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
    </form>
</div>

<div class="alert alert-info py-2 small d-flex gap-2 align-items-start">
    <i class="bi bi-info-circle mt-1"></i>
    <span>
        Esta pantalla <strong>no envía mensajes sola</strong>: abre WhatsApp con el texto ya escrito
        para que lo mandes, y guarda quién avisó y cuándo para que nadie llame dos veces.
    </span>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-0">
        <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
            <h6 class="fw-bold mb-0">Pendientes de avisar</h6>
            <span class="badge bg-warning">{{ $pending->count() }}</span>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Cuándo</th>
                        <th>Cliente</th>
                        <th>Servicio</th>
                        <th>Barbero</th>
                        <th class="text-end pe-3">Avisar</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($pending as $appointment)
                        @php $whatsapp = $appointment->whatsappUrl($company); @endphp
                        <tr>
                            <td class="ps-3 text-nowrap">
                                <span class="fw-semibold">{{ $appointment->starts_at->translatedFormat('D d/m') }}</span>
                                <small class="text-muted d-block">{{ $appointment->rangeLabel() }}</small>
                            </td>
                            <td>
                                <a href="{{ route('appointments.show', $appointment) }}" class="fw-semibold text-decoration-none">
                                    {{ $appointment->client?->full_name ?? '—' }}
                                </a>
                                @if($appointment->client?->phone)
                                    <small class="text-muted d-block">{{ $appointment->client->phone }}</small>
                                @else
                                    <small class="text-danger d-block">Sin teléfono</small>
                                @endif
                            </td>
                            <td><small>{{ $appointment->servicesLabel() }}</small></td>
                            <td><small>{{ $appointment->personal?->full_name }}</small></td>
                            <td class="text-end pe-3">
                                <div class="d-flex gap-1 justify-content-end">
                                    @if($whatsapp)
                                        <a href="{{ $whatsapp }}" target="_blank" rel="noopener"
                                           class="btn btn-sm btn-outline-success" title="Abrir WhatsApp con el mensaje escrito">
                                            <i class="bi bi-whatsapp"></i>
                                        </a>
                                    @endif
                                    @if($appointment->client?->phone)
                                        <a href="tel:{{ $appointment->client->phone }}" class="btn btn-sm btn-outline-secondary" title="Llamar">
                                            <i class="bi bi-telephone"></i>
                                        </a>
                                    @endif
                                    <form action="{{ route('reminders.store', $appointment) }}" method="POST">
                                        @csrf
                                        <button class="btn btn-sm btn-primary" title="Marcar como avisado">
                                            <i class="bi bi-check-lg"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">
                                No queda nadie por avisar en este plazo.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@if($done->isNotEmpty())
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0">Ya avisados</h6>
                <span class="badge bg-success">{{ $done->count() }}</span>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Cuándo</th>
                            <th>Cliente</th>
                            <th>Avisado</th>
                            <th class="text-end pe-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($done as $appointment)
                            <tr>
                                <td class="ps-3 text-nowrap">
                                    {{ $appointment->starts_at->translatedFormat('D d/m') }}
                                    <small class="text-muted d-block">{{ $appointment->rangeLabel() }}</small>
                                </td>
                                <td>{{ $appointment->client?->full_name ?? '—' }}</td>
                                <td class="text-muted">
                                    <small>
                                        {{ $appointment->reminded_at->translatedFormat('d/m H:i') }}
                                        @if($appointment->reminder)
                                            · {{ $appointment->reminder->name }}
                                        @endif
                                    </small>
                                </td>
                                <td class="text-end pe-3">
                                    <form action="{{ route('reminders.destroy', $appointment) }}" method="POST">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-secondary" title="Desmarcar">
                                            <i class="bi bi-arrow-counterclockwise"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endif
@endsection
