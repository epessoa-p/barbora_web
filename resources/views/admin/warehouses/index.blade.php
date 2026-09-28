@extends('layouts.app')

@section('title', 'Almacenes - Barbora')

@section('page')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h4 fw-bold mb-1"><i class="bi bi-box-seam"></i> Almacenes</h1>
        <p class="text-muted mb-0">
            Cada sucursal tiene su propio almacén, creado automáticamente. Puedes añadir otros aparte.
        </p>
    </div>
    <a href="{{ route('warehouses.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-circle"></i> Nuevo almacén
    </a>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Código</th>
                        <th>Almacén</th>
                        <th>Origen</th>
                        <th>Teléfono</th>
                        <th>Dirección</th>
                        <th class="text-center">Estado</th>
                        <th class="text-end pe-3">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($warehouses as $warehouse)
                        <tr>
                            <td class="ps-3"><code>{{ $warehouse->code }}</code></td>
                            <td class="fw-semibold">{{ $warehouse->name }}</td>
                            <td>
                                @if($warehouse->isManagedByBranch())
                                    <span class="badge bg-secondary-subtle text-secondary-emphasis border">
                                        <i class="bi bi-diagram-2"></i> {{ $warehouse->branch?->name ?? 'Sucursal' }}
                                    </span>
                                @else
                                    <span class="text-muted">Independiente</span>
                                @endif
                            </td>
                            <td>{{ $warehouse->phone ?: '—' }}</td>
                            <td>{{ $warehouse->address ?: '—' }}</td>
                            <td class="text-center">
                                <span class="badge {{ $warehouse->active ? 'bg-success' : 'bg-secondary' }}">
                                    {{ $warehouse->active ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>
                            <td class="text-end pe-3">
                                @if($warehouse->isManagedByBranch())
                                    <a href="{{ route('branches.edit', $warehouse->branch_id) }}"
                                       class="btn btn-sm btn-outline-secondary"
                                       title="Lo gestiona su sucursal: edítalo desde ahí">
                                        <i class="bi bi-diagram-2"></i> Ver sucursal
                                    </a>
                                @else
                                    <a href="{{ route('warehouses.edit', $warehouse) }}" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form action="{{ route('warehouses.destroy', $warehouse) }}" method="POST" class="d-inline"
                                          onsubmit="return confirm('¿Eliminar el almacén «{{ $warehouse->name }}»?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                Todavía no hay almacenes. Se crea uno solo al dar de alta una sucursal.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="d-flex justify-content-center mt-3">
    {{ $warehouses->links() }}
</div>
@endsection
