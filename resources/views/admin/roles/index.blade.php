@extends('layouts.app')

@section('page')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="bi bi-shield-lock"></i> Roles</h1>
    <a href="{{ route('roles.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-circle"></i> Nuevo Rol
    </a>
</div>

<div class="card">
    <div class="card-body">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Slug</th>
                    <th>Descripción</th>
                    <th>Permisos</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @foreach($roles as $role)
                    <tr>
                        <td><strong>{{ $role->name }}</strong></td>
                        <td><code>{{ $role->slug }}</code></td>
                        <td>{{ $role->description ?? '-' }}</td>
                        <td>{{ $role->permissions->count() }} permisos</td>
                        <td>
                            <a href="{{ route('roles.edit', $role) }}" class="btn btn-sm btn-warning">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form action="{{ route('roles.destroy', $role) }}" method="POST" style="display: inline;">
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
            {{ $roles->links() }}
        </div>
    </div>
</div>
@endsection
