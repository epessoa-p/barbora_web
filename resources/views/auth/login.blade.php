@extends('layouts.auth')

@section('title', 'Login - Barbora')

@section('content')
<div class="auth-container">
    <div class="auth-card">
        <div class="auth-brand">
            <img src="{{ asset('img/brand.svg') }}" alt="Barbora — Management Platform">
        </div>

        <div class="auth-header">
            <p>Inicia sesión para acceder</p>
        </div>

        @if($errors->any())
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle"></i>
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form action="{{ route('login.store') }}" method="POST">
            @csrf

            <div class="form-group">
                <label for="email" class="form-label">Correo o usuario</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                    <input type="text" id="email" name="email" class="form-control @error('email') is-invalid @enderror"
                           placeholder="correo@ejemplo.com o tu usuario" value="{{ old('email') }}" required autocomplete="username">
                </div>
            </div>

            <div class="form-group">
                <label for="password" class="form-label">Contraseña</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                    <input type="password" id="password" name="password" class="form-control @error('password') is-invalid @enderror"
                           placeholder="••••••••" required>
                </div>
            </div>

            <div class="remember-me">
                <input type="checkbox" id="remember" name="remember" value="1">
                <label for="remember" style="margin: 0; cursor: pointer;">Recuerda mi información</label>
            </div>

            <button type="submit" class="btn btn-login">
                <i class="bi bi-box-arrow-in-right"></i> Iniciar Sesión
            </button>
        </form>
    </div>
</div>
@endsection
