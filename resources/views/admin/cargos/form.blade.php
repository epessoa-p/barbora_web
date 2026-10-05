<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1">{{ $cargo ? 'Editar cargo' : 'Nuevo cargo' }}</h1>
            <p class="text-muted mb-0">
                Cada cargo tiene su propio rol de permisos dentro de esta empresa.
            </p>
        </div>
        <a href="{{ route('cargos.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show d-flex gap-2 align-items-start" role="alert">
            <i class="bi bi-exclamation-triangle-fill mt-1 flex-shrink-0"></i>
            <div>
                <strong>Por favor corrige los siguientes errores:</strong>
                <ul class="mb-0 mt-1 ps-3">
                    @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <form action="{{ $action }}" method="POST">
        @csrf
        @if($method !== 'POST') @method($method) @endif

        {{-- Datos del cargo: a todo el ancho, arriba --}}
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-3"><i class="bi bi-briefcase"></i> Datos del cargo</h6>
                <div class="row g-3">
                    @if($companies->count() > 1)
                        <div class="col-md-6">
                            <label class="form-label">Empresa</label>
                            <select name="company_id" class="form-select" required>
                                <option value="">Seleccionar empresa</option>
                                @foreach($companies as $company)
                                    <option value="{{ $company->id }}" {{ (string) old('company_id', $cargo?->company_id) === (string) $company->id ? 'selected' : '' }}>{{ $company->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div class="col-md-6">
                        <label class="form-label">Nombre del cargo <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $cargo?->name) }}" required>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="{{ $companies->count() > 1 ? 'col-12' : 'col-md-6' }}">
                        <label class="form-label">Descripción</label>
                        <textarea name="description" rows="2" class="form-control" placeholder="Funciones principales del cargo...">{{ old('description', $cargo?->description) }}</textarea>
                    </div>

                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="active" value="1" {{ old('active', $cargo?->active ?? true) ? 'checked' : '' }}>
                            <label class="form-check-label">Activo</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Permisos: a todo el ancho, abajo --}}
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <h6 class="fw-bold mb-0"><i class="bi bi-key"></i> Permisos del rol</h6>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-outline-primary" id="btn-select-all">
                            <i class="bi bi-check-all"></i> Todos
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-deselect-all">
                            <i class="bi bi-x-lg"></i> Ninguno
                        </button>
                    </div>
                </div>

                <div class="alert alert-info small py-2 mb-3">
                    <i class="bi bi-info-circle me-1"></i>
                    @if($cargo)
                        Marca los permisos que tendrá el personal con este cargo.
                    @else
                        Al guardar se creará un rol propio de esta empresa con los permisos que marques.
                    @endif
                </div>

                <div class="row g-3">
                    @foreach($permissions as $module => $modulePerms)
                        <div class="col-md-6 col-lg-4 col-xl-3">
                            <div class="border rounded-3 p-3 h-100">
                                <h6 class="text-uppercase text-muted small fw-bold mb-2">
                                    <i class="bi bi-folder me-1"></i> {{ ucfirst($module) }}
                                </h6>
                                @foreach($modulePerms as $permission)
                                    <div class="form-check mb-1">
                                        <input class="form-check-input perm-check" type="checkbox" name="permissions[]"
                                            value="{{ $permission->id }}" id="perm_{{ $permission->id }}"
                                            {{ in_array($permission->id, old('permissions', $cargo?->role?->permissions?->pluck('id')->toArray() ?? [])) ? 'checked' : '' }}>
                                        <label class="form-check-label small" for="perm_{{ $permission->id }}">
                                            {{ $permission->name }}
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-3 text-muted small">
                    <i class="bi bi-shield-check me-1"></i>
                    <span id="perm-count">{{ count(old('permissions', $cargo?->role?->permissions?->pluck('id')->toArray() ?? [])) }}</span> permisos seleccionados
                </div>
            </div>
        </div>

        <div class="mt-4 d-flex gap-2">
            <button class="btn btn-primary px-4" type="submit"><i class="bi bi-save"></i> {{ $cargo ? 'Guardar cambios' : 'Crear cargo' }}</button>
            <a href="{{ route('cargos.index') }}" class="btn btn-light border">Cancelar</a>
        </div>
    </form>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const permChecks = Array.from(document.querySelectorAll('.perm-check'));
    const permCount = document.getElementById('perm-count');

    function updateCount() {
        permCount.textContent = permChecks.filter((input) => input.checked).length;
    }

    permChecks.forEach(function (input) {
        input.addEventListener('change', updateCount);
    });

    document.getElementById('btn-select-all')?.addEventListener('click', function () {
        permChecks.forEach((input) => { input.checked = true; });
        updateCount();
    });

    document.getElementById('btn-deselect-all')?.addEventListener('click', function () {
        permChecks.forEach((input) => { input.checked = false; });
        updateCount();
    });

    updateCount();
});
</script>
@endpush
