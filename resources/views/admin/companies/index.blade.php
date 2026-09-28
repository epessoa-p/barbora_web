@extends('layouts.app')

@section('title', 'Empresas - Barbora')

@section('page')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="bi bi-building"></i> Empresas</h1>
        <a href="{{ route('companies.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Nueva Empresa
        </a>
    </div>

    <div class="card">
        <div class="card-body">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Id. fiscal</th>
                        <th>Plan</th>
                        <th>Suscripción</th>
                        <th>Email</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($companies as $company)
                        <tr>
                            <td><strong>{{ $company->name }}</strong></td>
                            <td>{{ $company->tax_id ?? '-' }}</td>
                            <td>{{ $company->subscription?->plan?->name ?? '—' }}</td>
                            <td>
                                @if($sub = $company->subscription)
                                    <span class="badge bg-{{ $sub->allowsWrite() ? 'success' : ($sub->allowsRead() ? 'warning' : 'danger') }}">
                                        {{ $sub->statusLabel() }}
                                    </span>
                                @else
                                    <span class="badge bg-danger">Sin plan</span>
                                @endif
                            </td>
                            <td>{{ $company->email ?? '-' }}</td>
                            <td>
                                <span class="badge {{ $company->active ? 'bg-success' : 'bg-danger' }}">
                                    {{ $company->active ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>
                            <td>
                                <a href="{{ route('companies.show', $company) }}" class="btn btn-sm btn-info">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="{{ route('companies.edit', $company) }}" class="btn btn-sm btn-warning">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form action="{{ route('companies.destroy', $company) }}" method="POST" style="display: inline;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('¿Estás seguro?')">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="d-flex justify-content-center">
                {{ $companies->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
