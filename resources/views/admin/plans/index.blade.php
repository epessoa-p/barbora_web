@extends('layouts.app')

@section('title', 'Planes - Barbora')

@section('page')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="mb-1"><i class="bi bi-box-seam"></i> Planes</h1>
        <p class="text-muted mb-0">Los productos comerciales que ofreces a las barberías.</p>
    </div>
    <a href="{{ route('plans.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-circle"></i> Nuevo plan
    </a>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Plan</th>
                    <th>Precio</th>
                    <th>Prueba</th>
                    <th>Límites</th>
                    <th>Módulos</th>
                    <th>Empresas</th>
                    <th>Estado</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($plans as $plan)
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $plan->name }}</div>
                            <small class="text-muted">{{ $plan->description ?: $plan->slug }}</small>
                        </td>
                        <td>
                            {{ number_format($plan->price, 2) }}
                            <small class="text-muted d-block">{{ $plan->billing_period === 'yearly' ? 'anual' : 'mensual' }}</small>
                        </td>
                        <td>{{ $plan->trial_days }} días</td>
                        <td>
                            <small class="d-block">Usuarios: {{ $plan->max_users ?? '∞' }}</small>
                            <small class="d-block">Sucursales: {{ $plan->max_branches ?? '∞' }}</small>
                            <small class="d-block">Productos: {{ $plan->max_products ?? '∞' }}</small>
                        </td>
                        <td>
                            @forelse($plan->features ?? [] as $feature)
                                <span class="badge bg-light text-dark border">{{ \App\Models\Plan::MODULES[$feature] ?? $feature }}</span>
                            @empty
                                <span class="text-muted">—</span>
                            @endforelse
                        </td>
                        <td>{{ $plan->subscriptions_count }}</td>
                        <td>
                            <span class="badge bg-{{ $plan->active ? 'success' : 'secondary' }}">
                                {{ $plan->active ? 'Activo' : 'Inactivo' }}
                            </span>
                        </td>
                        <td class="text-end">
                            <a href="{{ route('plans.edit', $plan) }}" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form action="{{ route('plans.destroy', $plan) }}" method="POST" class="d-inline"
                                  onsubmit="return confirm('¿Eliminar el plan «{{ $plan->name }}»?')">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">
                            Todavía no hay planes. Crea el primero para poder dar de alta empresas.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
