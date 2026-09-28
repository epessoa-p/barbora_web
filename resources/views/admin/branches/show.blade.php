@extends('layouts.app')

@section('title', 'Detalle sucursal')

@section('page')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="mb-1">{{ $branch->name }}</h1>
            <p class="text-muted mb-0">Detalle general de la sucursal.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('branches.edit', $branch) }}" class="btn btn-primary">Editar</a>
            <a href="{{ route('branches.index') }}" class="btn btn-outline-secondary">Volver</a>
        </div>
    </div>
    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="fw-bold mb-3">Datos generales</h6>
                    <p class="mb-1"><strong>Empresa:</strong> {{ $branch->company?->name }}</p>
                    <p class="mb-1"><strong>Código:</strong> {{ $branch->code ?: '-' }}</p>
                    <p class="mb-1"><strong>Encargado:</strong> {{ $branch->manager_name ?: '-' }}</p>
                    <p class="mb-1"><strong>Correo:</strong> {{ $branch->email ?: '-' }}</p>
                    <p class="mb-1"><strong>Teléfono:</strong> {{ $branch->phone ?: '-' }}</p>
                    <p class="mb-0"><strong>Dirección:</strong> {{ $branch->address ?: '-' }}</p>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="fw-bold mb-3">Cajas de la sucursal</h6>
                    @forelse($branch->cajas as $caja)
                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                            <div>
                                <div class="fw-semibold">{{ $caja->name }}</div>
                                <small class="text-muted">{{ $caja->code ?: 'Sin código' }}</small>
                            </div>
                            <span class="badge bg-{{ $caja->active ? 'success' : 'secondary' }}">
                                {{ $caja->active ? 'Activa' : 'Inactiva' }}
                            </span>
                        </div>
                    @empty
                        <p class="text-muted mb-0">Esta sucursal todavía no tiene cajas.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection