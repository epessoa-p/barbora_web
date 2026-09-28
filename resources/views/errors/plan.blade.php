@extends('layouts.app')

@section('title', 'Módulo no incluido en tu plan')

@section('page')
<div class="container-fluid">
    <div class="d-flex align-items-center justify-content-center" style="min-height: 60vh;">
        <div class="text-center" style="max-width: 32rem;">
            <h1 class="display-4 text-warning"><i class="bi bi-box-seam"></i></h1>
            <h2 class="mb-3">Módulo no incluido en tu plan</h2>
            <p class="text-muted mb-2">
                Esta sección requiere
                @if(count($modules) === 1)
                    el módulo
                @else
                    alguno de los módulos
                @endif
                @foreach($modules as $module)
                    <strong>{{ \App\Models\Plan::MODULES[$module] ?? $module }}</strong>{{ !$loop->last ? ' o ' : '' }}
                @endforeach
                y tu plan actual no lo incluye.
            </p>
            @if($company?->subscription?->plan)
                <p class="small text-muted mb-4">Plan actual: {{ $company->subscription->plan->name }}</p>
            @endif
            <a href="{{ route('dashboard') }}" class="btn btn-primary">
                <i class="bi bi-arrow-left"></i> Volver al Dashboard
            </a>
        </div>
    </div>
</div>
@endsection
