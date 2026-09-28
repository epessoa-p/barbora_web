@extends('layouts.app')

@section('title', 'Nueva cita - Barbora')

@section('page')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h4 fw-bold mb-1"><i class="bi bi-calendar-plus"></i> Nueva cita</h1>
        <p class="text-muted mb-0">Se comprueba que el barbero trabaje y tenga el hueco libre.</p>
    </div>
    <a href="{{ route('appointments.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Volver
    </a>
</div>

@include('appointments.form', ['appointment' => null])
@endsection
