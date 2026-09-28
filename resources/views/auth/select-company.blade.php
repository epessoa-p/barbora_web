@extends('layouts.auth')

@section('title', 'Seleccionar Empresa - Barbora')

@section('content')
<div class="auth-container">
    <div class="auth-card">
        <div class="auth-brand">
            <img src="{{ asset('img/brand.svg') }}" alt="Barbora">
        </div>

        <div class="auth-header">
            <h1>Selecciona una Empresa</h1>
            <p>Perteneces a múltiples empresas</p>
        </div>

        <div class="list-group">
            @foreach($companies as $company)
                <form action="{{ route('set-company', $company->id) }}" method="POST" style="display: inline-block; width: 100%; margin-bottom: 10px;">
                    @csrf
                    <button type="submit" class="list-group-item list-group-item-action p-3" style="text-align: left;">
                        <div class="d-flex">
                            <div>
                                <h6 class="mb-1 text-primary">{{ $company->name }}</h6>
                                <small class="text-muted">
                                    @if($company->tax_id)
                                        <span><i class="bi bi-receipt"></i> {{ $company->tax_id }}</span>
                                    @endif
                                    @if($company->email)
                                        <span class="ms-2"><i class="bi bi-envelope"></i> {{ $company->email }}</span>
                                    @endif
                                </small>
                            </div>
                            <div class="ms-auto text-end">
                                <i class="bi bi-chevron-right text-primary"></i>
                            </div>
                        </div>
                    </button>
                </form>
            @endforeach
        </div>

        <hr class="my-4">

        <form action="{{ route('logout') }}" method="POST">
            @csrf
            <button type="submit" class="btn btn-outline-secondary w-100">
                <i class="bi bi-box-arrow-left"></i> Usar otra cuenta
            </button>
        </form>
    </div>
</div>
@endsection
