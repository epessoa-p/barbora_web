@extends('layouts.app')

@section('title', 'Sucursales')

@section('page')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="mb-1"><i class="bi bi-diagram-2"></i> Sucursales</h1>
            <p class="text-muted mb-0">Administra las sucursales disponibles por empresa.</p>
        </div>
        <a href="{{ route('branches.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nueva sucursal</a>
    </div>
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Nombre</th>
                            <th>Código</th>
                            <th>Empresa</th>
                            <th>Encargado</th>
                            <th>Estado</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($branches as $branch)
                            <tr>
                                <td>{{ $branch->name }}</td>
                                <td>{{ $branch->code ?: '-' }}</td>
                                <td>{{ $branch->company?->name ?: '-' }}</td>
                                <td>{{ $branch->manager_name ?: '-' }}</td>
                                <td><span class="badge {{ $branch->active ? 'bg-success' : 'bg-secondary' }}">{{ $branch->active ? 'Activa' : 'Inactiva' }}</span></td>
                                <td class="text-end">
                                    <a href="{{ route('branches.show', $branch) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a>
                                    <a href="{{ route('branches.edit', $branch) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                    <form action="{{ route('branches.destroy', $branch) }}" method="POST" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('¿Eliminar sucursal?')"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center py-5 text-muted">No hay sucursales registradas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="mt-4 d-flex justify-content-center">{{ $branches->links() }}</div>
</div>
@endsection