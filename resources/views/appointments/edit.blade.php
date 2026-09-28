@extends('layouts.app')

@section('title', 'Editar cita - Barbora')

@section('page')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h4 fw-bold mb-1"><i class="bi bi-pencil"></i> Editar cita</h1>
        <p class="text-muted mb-0">
            {{ $appointment->client?->full_name }} ·
            {{ $appointment->starts_at->translatedFormat('l d \d\e F, H:i') }}
        </p>
    </div>
    <a href="{{ route('appointments.show', $appointment) }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Volver
    </a>
</div>

@include('appointments.form', ['appointment' => $appointment, 'defaults' => []])
@endsection
