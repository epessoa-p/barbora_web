@extends('layouts.app')

@section('page')
<h1><i class="bi bi-pencil"></i> Editar Usuario</h1>

<div class="card mt-4">
    <div class="card-body">
        <form action="{{ route('users.update', $user) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="form-group mb-3">
                <label for="name" class="form-label">Nombre</label>
                <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror" data-no-upper value="{{ old('name', $user->name) }}" required>
            </div>

            <div class="form-group mb-3">
                <label for="email" class="form-label">Email</label>
                {{-- Editable: un input «disabled» no se envía, y la validación
                     exige el email → antes guardar fallaba con «email requerido». --}}
                <input type="email" id="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $user->email) }}" required>
            </div>

            <div class="form-group mb-3">
                <label for="phone" class="form-label">Teléfono</label>
                <input type="text" id="phone" name="phone" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone', $user->phone) }}">
            </div>

            {{-- Cambiar la contraseña: solo el superadmin (Modo Global). Vacío = no se toca. --}}
            @if(auth()->user()->is_super_admin)
                <div class="border rounded p-3 mb-3 bg-light">
                    <h6 class="fw-bold mb-1"><i class="bi bi-key"></i> Nueva contraseña</h6>
                    <p class="small text-muted mb-3">Déjala vacía para no cambiarla. Mínimo 8 caracteres.</p>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="password" class="form-label">Contraseña</label>
                            <input type="password" id="password" name="password" autocomplete="new-password"
                                   class="form-control @error('password') is-invalid @enderror">
                            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label for="password_confirmation" class="form-label">Confirmar contraseña</label>
                            <input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" class="form-control">
                        </div>
                    </div>
                </div>
            @endif

            <div class="form-group">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-circle"></i> Actualizar
                </button>
                <a href="{{ route('users.index') }}" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Cancelar
                </a>
            </div>
        </form>
    </div>
</div>
@endsection
