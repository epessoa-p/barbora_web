@extends('layouts.app')

@section('title', 'Personal')

@section('page')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-person-vcard"></i> Personal</h1>
            <p class="text-muted mb-0">Gestiona al personal y su acceso al sistema mediante cargos.</p>
        </div>
        <a href="{{ route('personal.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Nuevo personal
        </a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Nombre</th>
                            <th>Cargo</th>
                            <th>Usuario</th>
                            <th>Empresa</th>
                            <th class="text-center">Estado</th>
                            <th class="text-end pe-3">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($personals as $personal)
                            <tr>
                                <td class="ps-3">
                                    <div class="d-flex align-items-center gap-2">
                                        @if($personal->photoUrl())
                                            <img src="{{ $personal->photoUrl() }}" alt="" class="staff-avatar">
                                        @else
                                            <span class="staff-avatar staff-avatar-initials"
                                                  style="background: {{ $personal->agenda_color }};">{{ $personal->initials() }}</span>
                                        @endif
                                        <div>
                                            <div class="fw-semibold">
                                                {{ $personal->full_name }}
                                                @if($personal->bookable)
                                                    <i class="bi bi-scissors text-primary ms-1" title="Atiende citas"></i>
                                                @endif
                                            </div>
                                            <small class="text-muted">{{ $personal->specialty ?: ($personal->id_number ?: 'Sin documento') }}</small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle">{{ $personal->cargo?->name ?? '-' }}</span>
                                    <div><small class="text-muted">Rol: {{ $personal->cargo?->role?->name ?? '-' }}</small></div>
                                </td>
                                <td>
                                    <div class="fw-semibold">{{ $personal->user?->name ?? '-' }}</div>
                                    <small class="text-muted">{{ $personal->user?->email ?? '-' }}</small>
                                </td>
                                <td>{{ $personal->company?->name ?? '-' }}</td>
                                <td class="text-center">
                                    <span class="badge {{ $personal->active ? 'bg-success' : 'bg-secondary' }}">{{ $personal->active ? 'Activo' : 'Inactivo' }}</span>
                                </td>
                                <td class="text-end pe-3">
                                    @if($personal->bookable)
                                        <a href="{{ route('schedules.show', $personal) }}" class="btn btn-sm btn-outline-secondary"
                                           title="Horario de trabajo"><i class="bi bi-calendar-week"></i></a>
                                    @endif
                                    <a href="{{ route('personal.edit', $personal) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                    <form action="{{ route('personal.destroy', $personal) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar registro de {{ addslashes($personal->full_name) }}?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center py-5 text-muted">No hay personal registrado.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white">{{ $personals->links() }}</div>
    </div>
</div>
@endsection
