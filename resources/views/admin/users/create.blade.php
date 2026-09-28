@extends('layouts.app')

@section('page')
<h1><i class="bi bi-plus-circle"></i> Nuevo Usuario</h1>

@if($errors->any())
    <div class="alert alert-danger mt-3">
        <ul class="mb-0 ps-3">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
@endif

<div class="card mt-4">
    <div class="card-body">
        <form action="{{ route('users.store') }}" method="POST">
            @csrf

            <div class="form-group mb-3">
                <label for="name" class="form-label">Nombre</label>
                <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required>
            </div>

            <div class="form-group mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" id="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" required>
            </div>

            <div class="form-group mb-3">
                <label for="password" class="form-label">Contraseña</label>
                <input type="password" id="password" name="password" class="form-control @error('password') is-invalid @enderror" required>
            </div>

            <div class="form-group mb-3">
                <label for="password_confirmation" class="form-label">Confirmar Contraseña</label>
                <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" required>
            </div>

            <div class="form-group mb-3">
                <label for="phone" class="form-label">Teléfono</label>
                <input type="text" id="phone" name="phone" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone') }}">
            </div>

            @if($canCreateSuperAdmin)
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" name="is_super_admin" id="is_super_admin"
                           value="1" {{ old('is_super_admin') ? 'checked' : '' }}>
                    <label class="form-check-label" for="is_super_admin">
                        Usuario de plataforma (superadmin, sin empresa)
                    </label>
                </div>
            @endif

            {{-- Membresía: sin empresa y rol el usuario no puede entrar a ninguna pantalla. --}}
            <div id="membership-block" class="border rounded-3 p-3 mb-3">
                <h6 class="fw-bold mb-3"><i class="bi bi-building"></i> Acceso</h6>

                @if($companies->count() > 1)
                    <div class="form-group mb-3">
                        <label for="company_id" class="form-label">Empresa</label>
                        <select id="company_id" name="company_id" class="form-select @error('company_id') is-invalid @enderror">
                            <option value="">Seleccionar empresa...</option>
                            @foreach($companies as $company)
                                <option value="{{ $company->id }}" {{ (string) old('company_id') === (string) $company->id ? 'selected' : '' }}>
                                    {{ $company->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('company_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                @else
                    <input type="hidden" id="company_id" name="company_id" value="{{ old('company_id', $companies->first()?->id) }}">
                @endif

                <div class="form-group mb-0">
                    <label for="role_id" class="form-label">Rol</label>
                    <select id="role_id" name="role_id" class="form-select @error('role_id') is-invalid @enderror">
                        <option value="">Seleccionar rol...</option>
                        @foreach($rolesByCompany as $companyId => $roles)
                            @foreach($roles as $role)
                                <option value="{{ $role->id }}" data-company="{{ $companyId }}"
                                    {{ (string) old('role_id') === (string) $role->id ? 'selected' : '' }}>
                                    {{ $role->name }}{{ $role->isSystem() ? '' : ' (propio)' }}
                                </option>
                            @endforeach
                        @endforeach
                    </select>
                    @error('role_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <small class="text-muted">Define qué puede hacer el usuario dentro de la empresa.</small>
                </div>
            </div>

            <div class="form-group">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-circle"></i> Crear Usuario
                </button>
                <a href="{{ route('users.index') }}" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Cancelar
                </a>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const companySelect = document.getElementById('company_id');
    const roleSelect = document.getElementById('role_id');
    const superAdmin = document.getElementById('is_super_admin');
    const block = document.getElementById('membership-block');
    if (!roleSelect) return;

    const allRoles = Array.from(roleSelect.querySelectorAll('option[data-company]'));

    // Cada empresa tiene sus propios roles: el select sólo muestra los suyos.
    function filterRoles() {
        const companyId = companySelect ? companySelect.value : '';
        allRoles.forEach(function (option) {
            const visible = option.dataset.company === companyId;
            option.hidden = !visible;
            if (!visible && option.selected) roleSelect.value = '';
        });
    }

    // Un usuario de plataforma no pertenece a ninguna empresa.
    function toggleMembership() {
        if (!superAdmin) return;
        block.classList.toggle('d-none', superAdmin.checked);
    }

    companySelect?.addEventListener('change', filterRoles);
    superAdmin?.addEventListener('change', toggleMembership);

    if (companySelect && companySelect.tagName === 'SELECT') filterRoles();
    toggleMembership();
});
</script>
@endpush
