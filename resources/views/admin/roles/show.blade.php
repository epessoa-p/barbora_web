@extends('layouts.app')

@section('page')
<h1><i class="bi bi-shield-lock"></i> {{ $role->name }}</h1>

<div class="card mt-4">
    <div class="card-header">
        <h5 class="mb-0">Información del Rol</h5>
    </div>
    <div class="card-body">
        <p><strong>Slug:</strong> <code>{{ $role->slug }}</code></p>
        <p><strong>Descripción:</strong> {{ $role->description ?? '-' }}</p>
    </div>
</div>

<div class="card mt-4">
    <div class="card-header">
        <h5 class="mb-0">Permisos ({{ $role->permissions->count() }})</h5>
    </div>
    <div class="card-body">
        @php
            $permissionsByModule = $role->permissions->groupBy('module');
        @endphp

        @foreach($permissionsByModule as $module => $permissions)
            <h6 class="mt-3 text-capitalize">{{ $module }}</h6>
            <div class="row">
                @foreach($permissions as $permission)
                    <div class="col-md-6 mb-2">
                        <span class="badge bg-primary">{{ $permission->name }}</span>
                    </div>
                @endforeach
            </div>
        @endforeach
    </div>
</div>

<a href="{{ route('roles.index') }}" class="btn btn-secondary mt-4">
    <i class="bi bi-arrow-left"></i> Volver
</a>
@endsection
