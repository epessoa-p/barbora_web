@extends('layouts.app')

@section('page')
<h1><i class="bi bi-building"></i> {{ $company->name }}</h1>

<div class="row mt-4">
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Información</h5>
            </div>
            <div class="card-body">
                <p><strong>{{ $company->tax_id_label ?? 'NIT' }}:</strong> {{ $company->tax_id ?? '-' }}</p>
                <p><strong>Email:</strong> {{ $company->email ?? '-' }}</p>
                <p><strong>Teléfono:</strong> {{ $company->phone ?? '-' }}</p>
                <p><strong>Dirección:</strong> {{ $company->address ?? '-' }}</p>
                <p><strong>País:</strong> {{ config("barbora.countries.{$company->country}.name", $company->country) }}</p>
                <p><strong>Moneda:</strong> {{ $company->currency }} ({{ \App\Support\Money::symbol($company) }})</p>
                <p><strong>Zona horaria:</strong> {{ $company->timezone }}</p>
                <p class="mb-0"><strong>Estado:</strong> <span class="badge {{ $company->active ? 'bg-success' : 'bg-danger' }}">{{ $company->active ? 'Activo' : 'Inactivo' }}</span></p>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        @php($subscription = $company->subscription)
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Suscripción</h5>
                @if($subscription)
                    <span class="badge bg-{{ $subscription->allowsWrite() ? 'success' : ($subscription->allowsRead() ? 'warning' : 'danger') }}">
                        {{ $subscription->statusLabel() }}
                    </span>
                @else
                    <span class="badge bg-danger">Sin plan</span>
                @endif
            </div>
            <div class="card-body">
                @if($subscription)
                    <p class="mb-1"><strong>Plan:</strong> {{ $subscription->plan?->name ?? '-' }}</p>
                    @if($subscription->onTrial())
                        <p class="mb-1"><strong>Prueba hasta:</strong> {{ $subscription->trial_ends_at->translatedFormat('d/m/Y') }}</p>
                    @elseif($subscription->current_period_end)
                        <p class="mb-1"><strong>Periodo hasta:</strong> {{ $subscription->current_period_end->translatedFormat('d/m/Y') }}</p>
                    @endif
                    <p class="mb-1">
                        <strong>Sucursales:</strong>
                        {{ $company->usageFor('branches') }} / {{ $company->effectiveLimit('branches') ?? '∞' }}
                    </p>
                    <p class="mb-3">
                        <strong>Usuarios:</strong>
                        {{ $company->usageFor('users') }} / {{ $company->effectiveLimit('users') ?? '∞' }}
                    </p>
                @else
                    <p class="text-muted">Esta empresa no puede entrar hasta que le asignes un plan.</p>
                @endif

                <a href="{{ route('companies.subscription.edit', $company) }}" class="btn btn-outline-primary w-100">
                    <i class="bi bi-receipt"></i> {{ $subscription ? 'Gestionar suscripción' : 'Asignar un plan' }}
                </a>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Acciones</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('set-company', $company->id) }}" method="POST" class="mb-2">
                    @csrf
                    <button type="submit" class="btn btn-outline-secondary w-100">
                        <i class="bi bi-eye"></i> Entrar como esta empresa
                    </button>
                </form>
                <a href="{{ route('companies.edit', $company) }}" class="btn btn-warning w-100 mb-2">
                    <i class="bi bi-pencil"></i> Editar
                </a>
                <form action="{{ route('companies.destroy', $company) }}" method="POST">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger w-100" onclick="return confirm('¿Estás seguro?')">
                        <i class="bi bi-trash"></i> Eliminar
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="card mt-4">
    <div class="card-header">
        <h5 class="mb-0">Usuarios de la Empresa</h5>
    </div>
    <div class="card-body">
        <table class="table">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Email</th>
                    <th>Rol</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                    <tr>
                        <td>{{ $user->name }}</td>
                        <td>{{ $user->email }}</td>
                        <td>{{ $user->pivot->role_id ? \App\Models\Role::find($user->pivot->role_id)->name : '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="text-center text-muted">No hay usuarios en esta empresa</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<a href="{{ route('companies.index') }}" class="btn btn-secondary mt-4">
    <i class="bi bi-arrow-left"></i> Volver
</a>
@endsection
