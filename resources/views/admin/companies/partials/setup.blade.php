{{--
    Puesta en marcha: lo que necesita una barbería para trabajar desde el
    primer día. Solo al crear. Lo procesa App\Support\CompanySetup.
--}}
@php
    $days = old('setup_days', [1, 2, 3, 4, 5, 6]);
    $setupOn = old('setup', '1') === '1';
@endphp

<div class="col-12">
    <hr class="my-2">
    <div class="form-check form-switch">
        <input type="hidden" name="setup" value="0">
        <input class="form-check-input" type="checkbox" id="setup" name="setup" value="1" {{ $setupOn ? 'checked' : '' }}>
        <label class="form-check-label fw-semibold" for="setup">
            Dejar la empresa lista para usar
        </label>
        <div class="form-text">
            Crea la sucursal principal (con su almacén), los cargos ADMINISTRADOR, RECEPCIONISTA y BARBERO
            con sus permisos, el responsable con su usuario, la caja y el horario. Los métodos de pago se crean siempre.
        </div>
    </div>
</div>

<div class="col-12" id="setup-fields" @unless($setupOn) style="display:none" @endunless>
    <div class="row g-3">

        {{-- Sucursal --}}
        <div class="col-12">
            <h6 class="text-uppercase text-muted small fw-bold mb-0 mt-2"><i class="bi bi-diagram-2"></i> Sucursal principal</h6>
            <small class="text-muted">Su almacén se crea automáticamente con el mismo nombre.</small>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="setup_branch_name">Nombre</label>
            <input type="text" id="setup_branch_name" name="setup_branch_name" data-upper
                   class="form-control @error('setup_branch_name') is-invalid @enderror"
                   value="{{ old('setup_branch_name', 'CENTRAL') }}">
            @error('setup_branch_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-5">
            <label class="form-label" for="setup_branch_address">Dirección</label>
            <input type="text" id="setup_branch_address" name="setup_branch_address" class="form-control"
                   value="{{ old('setup_branch_address') }}" placeholder="Si se deja vacía, la de la empresa">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="setup_branch_phone">Teléfono</label>
            <input type="text" id="setup_branch_phone" name="setup_branch_phone" class="form-control"
                   value="{{ old('setup_branch_phone') }}">
        </div>

        {{-- Responsable --}}
        <div class="col-12">
            <h6 class="text-uppercase text-muted small fw-bold mb-0 mt-3"><i class="bi bi-person-badge"></i> Responsable (dueño o administrador)</h6>
            <small class="text-muted">Entra con cargo ADMINISTRADOR: todos los permisos de la barbería.</small>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="setup_owner_name">Nombre completo</label>
            <input type="text" id="setup_owner_name" name="setup_owner_name" data-upper
                   class="form-control @error('setup_owner_name') is-invalid @enderror"
                   value="{{ old('setup_owner_name') }}">
            @error('setup_owner_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-3">
            <label class="form-label" for="setup_owner_id_number">CI / Documento</label>
            <input type="text" id="setup_owner_id_number" name="setup_owner_id_number" class="form-control"
                   value="{{ old('setup_owner_id_number') }}">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="setup_owner_phone">Teléfono</label>
            <input type="text" id="setup_owner_phone" name="setup_owner_phone" class="form-control"
                   value="{{ old('setup_owner_phone') }}">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="setup_owner_username">Nombre de usuario</label>
            <input type="text" id="setup_owner_username" name="setup_owner_username" autocomplete="off"
                   class="form-control @error('setup_owner_username') is-invalid @enderror"
                   value="{{ old('setup_owner_username') }}">
            @error('setup_owner_username')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <small class="text-muted">Con él (o con el correo) inicia sesión en la web y la app.</small>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="setup_owner_email">Correo</label>
            <input type="email" id="setup_owner_email" name="setup_owner_email" autocomplete="off"
                   class="form-control @error('setup_owner_email') is-invalid @enderror"
                   value="{{ old('setup_owner_email') }}">
            @error('setup_owner_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label class="form-label" for="setup_owner_password">Contraseña</label>
            <div class="input-group">
                <input type="text" id="setup_owner_password" name="setup_owner_password" autocomplete="new-password"
                       class="form-control font-monospace @error('setup_owner_password') is-invalid @enderror"
                       value="{{ old('setup_owner_password') }}">
                <button type="button" class="btn btn-outline-secondary" id="setup-generate" title="Generar una contraseña">
                    <i class="bi bi-shuffle"></i> Generar
                </button>
                @error('setup_owner_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <small class="text-muted">Mínimo 8 caracteres. Se mostrará al terminar para copiarla.</small>
        </div>
        <div class="col-12">
            <div class="form-check form-switch">
                <input type="hidden" name="setup_owner_bookable" value="0">
                <input class="form-check-input" type="checkbox" id="setup_owner_bookable" name="setup_owner_bookable" value="1"
                       {{ old('setup_owner_bookable', '1') === '1' ? 'checked' : '' }}>
                <label class="form-check-label" for="setup_owner_bookable">
                    El responsable también atiende citas (es barbero)
                </label>
                <div class="form-text">Se le pone el horario de atención de abajo, para que se le puedan reservar citas.</div>
            </div>
        </div>

        {{-- Caja --}}
        <div class="col-12">
            <h6 class="text-uppercase text-muted small fw-bold mb-0 mt-3"><i class="bi bi-cash-coin"></i> Caja</h6>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="setup_caja_name">Nombre de la caja</label>
            <input type="text" id="setup_caja_name" name="setup_caja_name" data-upper
                   class="form-control @error('setup_caja_name') is-invalid @enderror"
                   value="{{ old('setup_caja_name', 'CAJA PRINCIPAL') }}">
            @error('setup_caja_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6 d-flex align-items-end">
            <div class="form-check form-switch mb-2">
                <input type="hidden" name="setup_caja_to_owner" value="0">
                <input class="form-check-input" type="checkbox" id="setup_caja_to_owner" name="setup_caja_to_owner" value="1"
                       {{ old('setup_caja_to_owner', '1') === '1' ? 'checked' : '' }}>
                <label class="form-check-label" for="setup_caja_to_owner">Asignar la caja al responsable</label>
            </div>
        </div>

        {{-- Horario --}}
        <div class="col-12">
            <h6 class="text-uppercase text-muted small fw-bold mb-0 mt-3"><i class="bi bi-clock"></i> Horario de atención</h6>
        </div>
        <div class="col-md-6">
            <label class="form-label d-block">Días</label>
            <div class="btn-group flex-wrap" role="group">
                @foreach(\App\Support\CompanySetup::WEEKDAYS as $num => $label)
                    <input type="checkbox" class="btn-check" id="setup_day_{{ $num }}" name="setup_days[]" value="{{ $num }}"
                           {{ in_array($num, array_map('intval', (array) $days), true) ? 'checked' : '' }}>
                    <label class="btn btn-outline-primary btn-sm" for="setup_day_{{ $num }}">{{ $label }}</label>
                @endforeach
            </div>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="setup_open">Abre</label>
            <input type="time" id="setup_open" name="setup_open" class="form-control @error('setup_open') is-invalid @enderror"
                   value="{{ old('setup_open', '09:00') }}">
            @error('setup_open')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-3">
            <label class="form-label" for="setup_close">Cierra</label>
            <input type="time" id="setup_close" name="setup_close" class="form-control @error('setup_close') is-invalid @enderror"
                   value="{{ old('setup_close', '19:00') }}">
            @error('setup_close')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-12">
            <div class="form-check">
                <input type="hidden" name="setup_base_cargos" value="0">
                <input class="form-check-input" type="checkbox" id="setup_base_cargos" name="setup_base_cargos" value="1"
                       {{ old('setup_base_cargos', '1') === '1' ? 'checked' : '' }}>
                <label class="form-check-label" for="setup_base_cargos">
                    Crear también los cargos RECEPCIONISTA y BARBERO con sus permisos habituales
                </label>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    (() => {
        const toggle = document.getElementById('setup');
        const fields = document.getElementById('setup-fields');
        toggle?.addEventListener('change', () => { fields.style.display = toggle.checked ? '' : 'none'; });

        // Contraseña legible y sin caracteres que se confunden (0/O, 1/l).
        document.getElementById('setup-generate')?.addEventListener('click', () => {
            const chars = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
            const random = new Uint32Array(10);
            crypto.getRandomValues(random);
            document.getElementById('setup_owner_password').value =
                Array.from(random, (n) => chars[n % chars.length]).join('');
        });
    })();
</script>
@endpush
