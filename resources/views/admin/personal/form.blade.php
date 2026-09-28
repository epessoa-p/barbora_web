<div class="container-fluid" style="max-width: 1100px;">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1">{{ $personal ? 'Editar personal' : 'Nuevo personal' }}</h1>
            <p class="text-muted mb-0">Al crear personal se genera usuario automáticamente y se asigna rol según el cargo.</p>
        </div>
        <a href="{{ route('personal.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
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

    <form action="{{ $action }}" method="POST" class="row g-4" enctype="multipart/form-data">
        @csrf
        @if($method !== 'POST') @method($method) @endif

        <div class="col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-person-badge"></i> Datos del personal</h6>
                    <div class="row g-3">
                        @if($companies->count() > 1)
                            <div class="col-md-6">
                                <label class="form-label">Empresa</label>
                                <select name="company_id" id="company_id" class="form-select" required>
                                    <option value="">Seleccionar empresa</option>
                                    @foreach($companies as $company)
                                        <option value="{{ $company->id }}" {{ (string) old('company_id', $personal?->company_id) === (string) $company->id ? 'selected' : '' }}>{{ $company->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif

                        <div class="col-md-6">
                            <label class="form-label">Cargo <span class="text-danger">*</span></label>
                            <select name="cargo_id" class="form-select" required>
                                <option value="">Seleccionar cargo</option>
                                @foreach($cargos as $cargo)
                                    <option value="{{ $cargo->id }}" {{ (string) old('cargo_id', $personal?->cargo_id) === (string) $cargo->id ? 'selected' : '' }}>
                                        {{ $cargo->name }} · Rol: {{ $cargo->role?->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-8">
                            <label class="form-label">Nombre completo <span class="text-danger">*</span></label>
                            <input type="text" name="full_name" class="form-control" value="{{ old('full_name', $personal?->full_name) }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Documento</label>
                            <input type="text" name="id_number" class="form-control" value="{{ old('id_number', $personal?->id_number) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Teléfono</label>
                            <input type="text" name="phone" class="form-control" value="{{ old('phone', $personal?->phone) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Fecha de ingreso</label>
                            <input type="date" name="hire_date" class="form-control" value="{{ old('hire_date', optional($personal?->hire_date)->toDateString()) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Dirección</label>
                            <input type="text" name="address" class="form-control" value="{{ old('address', $personal?->address) }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notas</label>
                            <textarea name="notes" rows="2" class="form-control">{{ old('notes', $personal?->notes) }}</textarea>
                        </div>
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="active" value="1" {{ old('active', $personal?->active ?? true) ? 'checked' : '' }}>
                                <label class="form-check-label">Activo</label>
                            </div>
                        </div>

                        <div class="col-12"><hr class="my-1"></div>

                        <div class="col-12">
                            <h6 class="fw-bold mb-1"><i class="bi bi-scissors"></i> Ficha de barbero</h6>
                            <p class="text-muted small mb-0">
                                Solo hace falta para quienes atienden clientes. La agenda usará su color
                                y su horario.
                            </p>
                        </div>

                        <div class="col-md-8">
                            <label class="form-label">Especialidad</label>
                            <input type="text" name="specialty" class="form-control @error('specialty') is-invalid @enderror"
                                   value="{{ old('specialty', $personal?->specialty) }}"
                                   placeholder="Fade, barba clásica, color…">
                            @error('specialty')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Color en la agenda</label>
                            <input type="color" name="agenda_color"
                                   class="form-control form-control-color w-100 @error('agenda_color') is-invalid @enderror"
                                   value="{{ old('agenda_color', $personal?->agenda_color ?? '#2563eb') }}">
                            @error('agenda_color')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-8">
                            <label class="form-label">Foto</label>
                            <input type="file" name="photo" accept="image/*"
                                   class="form-control @error('photo') is-invalid @enderror">
                            @error('photo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <small class="text-muted">JPG, PNG o WebP, hasta 2 MB. Si no subes ninguna se usan sus iniciales.</small>
                        </div>

                        <div class="col-md-4 d-flex align-items-end">
                            @if($personal?->photoUrl())
                                <img src="{{ $personal->photoUrl() }}" alt="Foto actual" class="staff-avatar staff-avatar-lg">
                            @elseif($personal)
                                <span class="staff-avatar staff-avatar-lg staff-avatar-initials"
                                      style="background: {{ $personal->agenda_color }};">{{ $personal->initials() }}</span>
                            @endif
                        </div>

                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="bookable" value="1"
                                       {{ old('bookable', $personal?->bookable ?? false) ? 'checked' : '' }}>
                                <label class="form-check-label">
                                    Atiende citas — aparece en la agenda y en Horarios
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            @if($personal?->bookable)
                <div class="card border-0 shadow-sm mt-4">
                    <div class="card-body p-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <h6 class="fw-bold mb-1"><i class="bi bi-calendar-week"></i> Horario de trabajo</h6>
                            <p class="text-muted small mb-0">
                                @php($minutes = $personal->weeklyMinutes())
                                @if($minutes > 0)
                                    {{ intdiv($minutes, 60) }} h a la semana en
                                    {{ $personal->schedules->where('active', true)->count() }} franjas.
                                @else
                                    Todavía sin franjas definidas.
                                @endif
                            </p>
                        </div>
                        <a href="{{ route('schedules.show', $personal) }}" class="btn btn-outline-primary">
                            Gestionar horario
                        </a>
                    </div>
                </div>
            @endif
        </div>

        <div class="col-lg-5">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-person-gear"></i> Datos del usuario</h6>
                    <div class="alert alert-info small">
                        El nombre de usuario se genera automáticamente con base en el nombre completo.
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Email de acceso <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $personal?->user?->email ?? $personal?->email) }}" required>
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Contraseña {{ $personal ? '(opcional para cambiar)' : '' }} <span class="text-danger">{{ $personal ? '' : '*' }}</span></label>
                        <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" {{ $personal ? '' : 'required' }}>
                        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Confirmar contraseña {{ $personal ? '(si cambiaste)' : '' }}</label>
                        <input type="password" name="password_confirmation" class="form-control" {{ $personal ? '' : 'required' }}>
                    </div>

                    <button class="btn btn-primary w-100" type="submit">
                        <i class="bi bi-save"></i> {{ $personal ? 'Guardar cambios' : 'Crear personal y usuario' }}
                    </button>
                    <a href="{{ route('personal.index') }}" class="btn btn-light border w-100 mt-2">Cancelar</a>
                </div>
            </div>
        </div>
    </form>
</div>
