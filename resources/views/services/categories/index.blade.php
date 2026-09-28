@extends('layouts.app')

@section('title', 'Categorías de servicio - Barbora')

@section('page')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h4 fw-bold mb-1"><i class="bi bi-tags"></i> Categorías de servicio</h1>
        <p class="text-muted mb-0">Agrupan el catálogo y dan color a la agenda.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('services.index') }}" class="btn btn-light border">
            <i class="bi bi-arrow-left"></i> Servicios
        </a>
        <a href="{{ route('service-categories.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Nueva categoría
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Categoría</th>
                        <th>Descripción</th>
                        <th class="text-center">Servicios</th>
                        <th class="text-center">Estado</th>
                        <th class="text-end pe-3">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($categories as $category)
                        <tr>
                            <td class="ps-3">
                                <span class="d-inline-flex align-items-center gap-2">
                                    <span class="d-inline-block rounded-circle"
                                          style="width:.75rem;height:.75rem;background:{{ $category->color }};"></span>
                                    <span class="fw-semibold">{{ $category->name }}</span>
                                </span>
                            </td>
                            <td class="text-muted">{{ $category->description ?: '—' }}</td>
                            <td class="text-center">{{ $category->services_count }}</td>
                            <td class="text-center">
                                <span class="badge {{ $category->active ? 'bg-success' : 'bg-secondary' }}">
                                    {{ $category->active ? 'Activa' : 'Inactiva' }}
                                </span>
                            </td>
                            <td class="text-end pe-3">
                                <a href="{{ route('service-categories.edit', $category) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form action="{{ route('service-categories.destroy', $category) }}" method="POST" class="d-inline"
                                      onsubmit="return confirm('¿Eliminar la categoría «{{ $category->name }}»?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">
                                Todavía no hay categorías. Son opcionales, pero ordenan el catálogo.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
