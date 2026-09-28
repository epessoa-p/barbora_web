<div class="container-fluid" style="max-width: 1100px;">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1">{{ $cargo ? 'Editar cargo' : 'Nuevo cargo' }}</h1>
            <p class="text-muted mb-0">Asocia un cargo a un rol para heredar permisos automáticamente.</p>
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

    <form action="{{ $action }}" method="POST" class="row g-4">
        @csrf
        @if($method !== 'POST') @method($method) @endif

        {{-- Left column: Cargo data + Role --}}
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-briefcase"></i> Datos del cargo</h6>
                    <div class="row g-3">
                        @if($companies->count() > 1)
                            <div class="col-12">
                                <label class="form-label">Empresa</label>
                                <select name="company_id" class="form-select" required>
                                    <option value="">Seleccionar empresa</option>
                                    @foreach($companies as $company)
                                        <option value="{{ $company->id }}" {{ (string) old('company_id', $cargo?->company_id) === (string) $company->id ? 'selected' : '' }}>{{ $company->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif

                        <div class="col-12">
                            <label class="form-label">Nombre del cargo <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $cargo?->name) }}" required>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12">
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

            {{-- Role selection / creation --}}
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-shield-lock"></i> Rol asociado</h6>

                    <div class="btn-group w-100 mb-3" role="group">
                        <input type="radio" class="btn-check" name="role_mode" id="mode_existing" value="existing"
                            {{ old('role_mode', 'existing') === 'existing' ? 'checked' : '' }} autocomplete="off">
                        <label class="btn btn-outline-primary" for="mode_existing">
                            <i class="bi bi-list-check me-1"></i> Rol existente
                        </label>

                        <input type="radio" class="btn-check" name="role_mode" id="mode_new" value="new"
                            {{ old('role_mode') === 'new' ? 'checked' : '' }} autocomplete="off">
                        <label class="btn btn-outline-primary" for="mode_new">
                            <i class="bi bi-plus-circle me-1"></i> Crear nuevo rol
                        </label>
                    </div>

                    {{-- Existing role --}}
                    <div id="existing-role-section">
                        <label class="form-label">Seleccionar rol</label>
                        <select name="role_id" id="role_id" class="form-select @error('role_id') is-invalid @enderror">
                            <option value="">Seleccionar rol...</option>
                            @foreach($roles as $role)
                                <option value="{{ $role->id }}" data-system="{{ $role->isSystem() ? '1' : '0' }}"
                                    {{ (string) old('role_id', $cargo?->role_id) === (string) $role->id ? 'selected' : '' }}>
                                    {{ $role->name }} — {{ $role->isSystem() ? 'del sistema' : 'propio' }}
                                </option>
                            @endforeach
                        </select>
                        @error('role_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <small class="text-muted">Los permisos del rol serán heredados por el personal con este cargo.</small>
                    </div>

                    {{-- New role --}}
                    <div id="new-role-section" class="d-none">
                        <label class="form-label">Nombre del nuevo rol <span class="text-danger">*</span></label>
                        <input type="text" name="new_role_name" id="new_role_name" class="form-control @error('new_role_name') is-invalid @enderror"
                            value="{{ old('new_role_name') }}" placeholder="Ej: Supervisor">
                        @error('new_role_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <small class="text-muted">El slug se generará automáticamente.</small>
                    </div>
                </div>
            </div>

            <div class="mt-3 d-flex gap-2">
                <button class="btn btn-primary px-4" type="submit"><i class="bi bi-save"></i> {{ $cargo ? 'Guardar cambios' : 'Crear cargo' }}</button>
                <a href="{{ route('cargos.index') }}" class="btn btn-light border">Cancelar</a>
            </div>
        </div>

        {{-- Right column: Permissions panel --}}
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
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
                    <div class="alert alert-info small py-2 mb-3" id="permissions-alert">
                        <i class="bi bi-info-circle me-1"></i>
                        <span id="permissions-info">Selecciona un rol o crea uno nuevo para configurar permisos.</span>
                    </div>

                    {{-- Un rol del sistema lo comparten todas las empresas: desde aquí
                         sólo se mira. Para permisos a medida, "Crear nuevo rol". --}}
                    <div class="alert alert-warning small py-2 mb-3 d-none" id="permissions-locked">
                        <i class="bi bi-lock me-1"></i>
                        Este rol es del sistema y lo comparten todas las empresas, así que sus
                        permisos no se editan desde aquí. Si necesitas otros permisos, usa
                        <strong>Crear nuevo rol</strong>.
                    </div>

                    <div class="row g-3" id="permissions-container">
                        @foreach($permissions as $module => $modulePerms)
                            <div class="col-md-6">
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
        </div>
    </form>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const roleSelect = document.getElementById('role_id');
    const existingSection = document.getElementById('existing-role-section');
    const newSection = document.getElementById('new-role-section');
    const modeNew = document.getElementById('mode_new');
    const permChecks = Array.from(document.querySelectorAll('.perm-check'));
    const permCount = document.getElementById('perm-count');
    const permInfo = document.getElementById('permissions-info');
    const permAlert = document.getElementById('permissions-alert');
    const permLocked = document.getElementById('permissions-locked');
    const selectAll = document.getElementById('btn-select-all');
    const deselectAll = document.getElementById('btn-deselect-all');
    const permissionsUrl = '{{ route("cargos.role-permissions", ":id") }}';

    function updateCount() {
        permCount.textContent = permChecks.filter((input) => input.checked).length;
    }

    // Los roles del sistema se muestran, pero no se tocan: el servidor rechaza
    // el cambio igualmente, esto sólo evita que el usuario lo descubra al guardar.
    function setLocked(locked) {
        permChecks.forEach((input) => { input.disabled = locked; });
        if (selectAll) selectAll.disabled = locked;
        if (deselectAll) deselectAll.disabled = locked;
        permLocked.classList.toggle('d-none', !locked);
        permAlert.classList.toggle('d-none', locked);
    }

    function selectedIsSystem() {
        const option = roleSelect?.selectedOptions[0];
        return !!option && option.dataset.system === '1';
    }

    function toggleMode() {
        if (modeNew.checked) {
            existingSection.classList.add('d-none');
            newSection.classList.remove('d-none');
            permInfo.textContent = 'Configura los permisos para el nuevo rol.';
            setLocked(false);
        } else {
            existingSection.classList.remove('d-none');
            newSection.classList.add('d-none');
            permInfo.textContent = roleSelect.value
                ? 'Permisos del rol seleccionado (puedes modificarlos).'
                : 'Selecciona un rol para ver sus permisos.';
            setLocked(roleSelect.value ? selectedIsSystem() : false);
        }
    }

    document.querySelectorAll('input[name="role_mode"]').forEach(function (input) {
        input.addEventListener('change', toggleMode);
    });

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

    // Al elegir un rol existente, trae sus permisos actuales.
    roleSelect?.addEventListener('change', function () {
        if (!this.value) {
            permInfo.textContent = 'Selecciona un rol para ver sus permisos.';
            return;
        }

        permInfo.textContent = 'Cargando permisos...';

        fetch(permissionsUrl.replace(':id', this.value), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then(function (response) {
                if (!response.ok) throw new Error(response.statusText);
                return response.json();
            })
            .then(function (payload) {
                permChecks.forEach((input) => { input.disabled = false; input.checked = false; });
                (payload.permissions || []).forEach(function (id) {
                    const input = document.getElementById('perm_' + id);
                    if (input) input.checked = true;
                });
                updateCount();
                permInfo.textContent = payload.editable
                    ? 'Permisos del rol seleccionado (puedes modificarlos).'
                    : 'Permisos del rol seleccionado.';
                setLocked(!payload.editable);
            })
            .catch(function () {
                permInfo.textContent = 'Error al cargar permisos.';
            });
    });

    toggleMode();
    updateCount();
});
</script>
@endpush
