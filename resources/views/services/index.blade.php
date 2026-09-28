@extends('layouts.app')

@section('title', 'Servicios - Barbora')

@section('page')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h4 fw-bold mb-1"><i class="bi bi-scissors"></i> Servicios</h1>
        <p class="text-muted mb-0">Qué ofrece la barbería, cuánto dura y cuánto cuesta.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('service-categories.index') }}" class="btn btn-light border">
            <i class="bi bi-tags"></i> Categorías
        </a>
        <a href="{{ route('services.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Nuevo servicio
        </a>
    </div>
</div>

<form method="GET" class="row g-2 mb-3">
    <div class="col-md-5">
        <input type="search" name="q" class="form-control" placeholder="Buscar por nombre…"
               value="{{ request('q') }}">
    </div>
    <div class="col-md-4">
        <select name="category" class="form-select">
            <option value="">Todas las categorías</option>
            @foreach($categories as $category)
                <option value="{{ $category->id }}" {{ (string) request('category') === (string) $category->id ? 'selected' : '' }}>
                    {{ $category->name }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3 d-flex gap-2">
        <button class="btn btn-outline-secondary flex-fill"><i class="bi bi-search"></i> Filtrar</button>
        @if(request('q') || request('category'))
            <a href="{{ route('services.index') }}" class="btn btn-light border">Limpiar</a>
        @endif
    </div>
</form>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Servicio</th>
                        <th>Categoría</th>
                        <th class="text-center">Duración</th>
                        <th class="text-end">Precio</th>
                        <th class="text-center">Estado</th>
                        <th class="text-end pe-3">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($services as $service)
                        <tr>
                            <td class="ps-3">
                                <div class="fw-semibold">{{ $service->name }}</div>
                                @if($service->description)
                                    <small class="text-muted">{{ $service->description }}</small>
                                @endif
                            </td>
                            <td>
                                @if($service->category)
                                    <span class="badge rounded-pill"
                                          style="background: {{ $service->category->color }}1a; color: {{ $service->category->color }};">
                                        {{ $service->category->name }}
                                    </span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-center">{{ $service->durationLabel() }}</td>
                            <td class="text-end fw-semibold">{{ \App\Support\Money::format($service->price, $tenantCompany) }}</td>
                            <td class="text-center">
                                <span class="badge {{ $service->active ? 'bg-success' : 'bg-secondary' }}">
                                    {{ $service->active ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>
                            <td class="text-end pe-3">
                                <a href="{{ route('services.edit', $service) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form action="{{ route('services.destroy', $service) }}" method="POST" class="d-inline"
                                      onsubmit="return confirm('¿Eliminar el servicio «{{ $service->name }}»?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                @if(request('q') || request('category'))
                                    Ningún servicio coincide con el filtro.
                                @else
                                    Todavía no hay servicios. Crea el primero para poder agendar y cobrar.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="d-flex justify-content-center mt-3">
    {{ $services->links() }}
</div>
@endsection
