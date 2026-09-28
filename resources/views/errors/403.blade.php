@extends('layouts.app')

@section('title', 'Acceso Denegado')

@section('page')
<div class="container-fluid">
    <div class="d-flex align-items-center justify-content-center" style="min-height: 60vh;">
        <div class="text-center">
            <h1 class="display-4 text-danger"><i class="bi bi-shield-exclamation"></i></h1>
            <h2 class="mb-3">Acceso Denegado</h2>
            <p class="text-muted mb-4">No tienes permisos para acceder a esta sección.</p>
            <a href="{{ route('dashboard') }}" class="btn btn-primary">
                <i class="bi bi-arrow-left"></i> Volver al Dashboard
            </a>
        </div>
    </div>
</div>
@endsection
