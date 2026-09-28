{{-- Usa el layout de autenticación: el de la aplicación consulta plan y
     permisos, que aquí no están disponibles. --}}
@extends('layouts.auth')

@section('title', 'Suscripción inactiva - Barbora')

@section('content')
<div class="auth-container">
<div class="auth-card text-center">
    <div class="auth-brand">
        <img src="{{ asset('img/brand.svg') }}" alt="Barbora">
    </div>

    <div class="mb-3">
        <i class="bi bi-lock-fill" style="font-size: 3rem; color: #dc3545;"></i>
    </div>

    <h1 class="h4 mb-3">Tu suscripción no está activa</h1>

    @if(! $subscription)
        <p class="text-muted">
            La empresa <strong>{{ $company?->name }}</strong> todavía no tiene un plan asignado.
            Contacta con tu proveedor para activar el servicio.
        </p>
    @elseif($subscription->isBlocked())
        <p class="text-muted">
            La suscripción de <strong>{{ $company?->name }}</strong> está
            <strong>{{ strtolower(\App\Models\Subscription::STATUSES[$subscription->status]) }}</strong>.
            Contacta con tu proveedor para reactivarla.
        </p>
    @else
        <p class="text-muted">
            La suscripción de <strong>{{ $company?->name }}</strong> venció
            @if($graceEnd = $subscription->graceEndsAt())
                el {{ $graceEnd->translatedFormat('d/m/Y') }}
            @endif
            y ya pasó el periodo de gracia. Renuévala para volver a entrar.
        </p>
    @endif

    @if($subscription?->plan)
        <p class="small text-muted">Plan contratado: {{ $subscription->plan->name }}</p>
    @endif

    <div class="d-flex justify-content-center gap-2 mt-4">
        <a href="{{ route('select-company') }}" class="btn btn-outline-secondary">
            <i class="bi bi-buildings"></i> Cambiar de empresa
        </a>
        <form action="{{ route('logout') }}" method="POST" class="m-0">
            @csrf
            <button class="btn btn-primary" type="submit">
                <i class="bi bi-box-arrow-right"></i> Cerrar sesión
            </button>
        </form>
    </div>
</div>
</div>
@endsection
