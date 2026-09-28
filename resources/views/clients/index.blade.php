@extends('layouts.app')

@section('title', 'Clientes - Barbora')

@section('page')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h4 fw-bold mb-1"><i class="bi bi-people"></i> Clientes</h1>
        <p class="text-muted mb-0">A quién atiende la barbería.</p>
    </div>
    <a href="{{ route('clients.create') }}" class="btn btn-primary">
        <i class="bi bi-person-plus"></i> Nuevo cliente
    </a>
</div>

<form method="GET" class="row g-2 mb-3">
    <div class="col-md-6">
        <div class="input-group">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="search" name="q" class="form-control"
                   placeholder="Buscar por nombre, teléfono, documento o correo…" value="{{ $q }}">
        </div>
    </div>
    <div class="col-md-3 d-flex gap-2">
        <button class="btn btn-outline-secondary">Buscar</button>
        @if($q)
            <a href="{{ route('clients.index') }}" class="btn btn-light border">Limpiar</a>
        @endif
    </div>
</form>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Cliente</th>
                        <th>Teléfono</th>
                        <th>Correo</th>
                        <th>Cumpleaños</th>
                        <th class="text-center">Estado</th>
                        <th class="text-end pe-3">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($clients as $client)
                        <tr>
                            <td class="ps-3">
                                <a href="{{ route('clients.show', $client) }}" class="fw-semibold text-decoration-none">
                                    {{ $client->full_name }}
                                </a>
                                @if($client->allergies)
                                    <i class="bi bi-exclamation-triangle-fill text-warning ms-1"
                                       title="Alergias: {{ $client->allergies }}"></i>
                                @endif
                                @if($client->document_number)
                                    <small class="text-muted d-block">{{ $client->document_number }}</small>
                                @endif
                            </td>
                            <td>{{ $client->phone ?: '—' }}</td>
                            <td>{{ $client->email ?: '—' }}</td>
                            <td>
                                @if($client->birth_date)
                                    {{ $client->birth_date->translatedFormat('d M') }}
                                    @if($client->birthdayIsNear())
                                        <span class="badge bg-info ms-1">Pronto</span>
                                    @endif
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <span class="badge {{ $client->active ? 'bg-success' : 'bg-secondary' }}">
                                    {{ $client->active ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>
                            <td class="text-end pe-3">
                                <a href="{{ route('clients.show', $client) }}" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="{{ route('clients.edit', $client) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form action="{{ route('clients.destroy', $client) }}" method="POST" class="d-inline"
                                      onsubmit="return confirm('¿Eliminar a «{{ $client->full_name }}»?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                @if($q)
                                    Ningún cliente coincide con «{{ $q }}».
                                @else
                                    Todavía no hay clientes registrados.
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
    {{ $clients->links() }}
</div>
@endsection
