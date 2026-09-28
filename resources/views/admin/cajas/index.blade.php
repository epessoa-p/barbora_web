@extends('layouts.app')

@section('title', 'Cajas')

@section('page')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-safe"></i> Cajas</h1>
            <p class="text-muted mb-0">Administra las cajas registradoras o fondos de la empresa.</p>
        </div>
        <a href="{{ route('cajas.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Nueva Caja
        </a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Caja</th>
                            <th>Sucursal</th>
                            <th class="text-end">Balance</th>
                            <th class="text-center">Estado</th>
                            <th class="text-end pe-3">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($cajas as $caja)
                            <tr>
                                <td class="ps-3">
                                    <div class="fw-semibold">{{ $caja->name }}</div>
                                    <small class="text-muted">{{ $caja->code ? 'Código: '.$caja->code : ($caja->description ?: 'Sin descripción') }}</small>
                                </td>
                                <td>
                                    @if($caja->branch)
                                        {{ $caja->branch->name }}
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-end fw-semibold {{ $caja->balance >= 0 ? 'text-success' : 'text-danger' }}">
                                    {{ \App\Support\Money::format($caja->balance, $tenantCompany) }}
                                </td>
                                <td class="text-center">
                                    <span class="badge {{ $caja->active ? 'bg-success' : 'bg-secondary' }}">{{ $caja->active ? 'Activa' : 'Inactiva' }}</span>
                                </td>
                                <td class="text-end pe-3">
                                    <a href="{{ route('cajas.edit', $caja) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                    <form action="{{ route('cajas.destroy', $caja) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar caja {{ addslashes($caja->name) }}?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center py-5 text-muted">No hay cajas registradas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
