@extends('layouts.app')

@section('title', $client->full_name.' - Barbora')

@section('page')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h4 fw-bold mb-1">{{ $client->full_name }}</h1>
        <p class="text-muted mb-0">
            Cliente desde {{ $client->created_at->translatedFormat('F Y') }}
            @unless($client->active) · <span class="badge bg-secondary">Inactivo</span> @endunless
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('clients.edit', $client) }}" class="btn btn-primary"><i class="bi bi-pencil"></i> Editar</a>
        <a href="{{ route('clients.index') }}" class="btn btn-outline-secondary">Volver</a>
    </div>
</div>

@if($client->allergies)
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <strong>Alergias:</strong> {{ $client->allergies }}
    </div>
@endif

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="fw-bold mb-3">Contacto</h6>
                <p class="mb-1"><strong>Teléfono:</strong> {{ $client->phone ?: '—' }}</p>
                <p class="mb-1"><strong>Correo:</strong> {{ $client->email ?: '—' }}</p>
                <p class="mb-1"><strong>Documento:</strong> {{ $client->document_number ?: '—' }}</p>
                <p class="mb-0">
                    <strong>Cumpleaños:</strong>
                    @if($client->birth_date)
                        {{ $client->birth_date->translatedFormat('d \d\e F') }}
                        @if($client->birthdayIsNear())
                            <span class="badge bg-info">Esta semana</span>
                        @endif
                    @else
                        —
                    @endif
                </p>
            </div>
        </div>

        <div class="card border-0 shadow-sm mt-4">
            <div class="card-body">
                <h6 class="fw-bold mb-3">Preferencias y notas</h6>
                <p class="mb-1"><strong>Preferencias:</strong> {{ $client->preferences ?: '—' }}</p>
                <p class="mb-0"><strong>Notas:</strong> {{ $client->notes ?: '—' }}</p>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <i class="bi bi-clock-history text-muted" style="font-size: 2.5rem;"></i>
                <h6 class="fw-semibold mt-3">Historial de atenciones</h6>
                <p class="text-muted mb-0">
                    Se irá llenando solo en cuanto estén los módulos de Citas y Ventas.
                </p>
            </div>
        </div>
    </div>
</div>
@endsection
