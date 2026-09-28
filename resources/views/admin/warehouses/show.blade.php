@extends('layouts.app')

@section('title', 'Detalle almacén - Barbora')

@section('page')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h4 fw-bold mb-1">{{ $warehouse->name }}</h1>
        <p class="text-muted mb-0"><code>{{ $warehouse->code }}</code></p>
    </div>
    <div class="d-flex gap-2">
        @unless($warehouse->isManagedByBranch())
            <a href="{{ route('warehouses.edit', $warehouse) }}" class="btn btn-primary">Editar</a>
        @endunless
        <a href="{{ route('warehouses.index') }}" class="btn btn-outline-secondary">Volver</a>
    </div>
</div>

@if($warehouse->isManagedByBranch())
    <div class="alert alert-info">
        <i class="bi bi-info-circle"></i>
        Este almacén lo mantiene la sucursal <strong>{{ $warehouse->branch?->name }}</strong>.
        Para cambiar su nombre, teléfono o dirección,
        <a href="{{ route('branches.edit', $warehouse->branch_id) }}">edita la sucursal</a>.
    </div>
@endif

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <p class="mb-1"><strong>Teléfono:</strong> {{ $warehouse->phone ?: '—' }}</p>
        <p class="mb-1"><strong>Dirección:</strong> {{ $warehouse->address ?: '—' }}</p>
        <p class="mb-1"><strong>Descripción:</strong> {{ $warehouse->description ?: '—' }}</p>
        <p class="mb-0">
            <strong>Estado:</strong>
            <span class="badge {{ $warehouse->active ? 'bg-success' : 'bg-secondary' }}">
                {{ $warehouse->active ? 'Activo' : 'Inactivo' }}
            </span>
        </p>
    </div>
</div>
@endsection
