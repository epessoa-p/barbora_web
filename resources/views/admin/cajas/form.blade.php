<div class="container-fluid" style="max-width: 700px;">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1">{{ $caja ? 'Editar caja' : 'Nueva caja' }}</h1>
            <p class="text-muted mb-0">{{ $caja ? 'Modifica los datos de la caja.' : 'Registra una nueva caja en la empresa.' }}</p>
        </div>
        <a href="{{ route('cajas.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
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

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-3"><i class="bi bi-safe"></i> Datos de la caja</h6>
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label">Nombre <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                            value="{{ old('name', $caja?->name) }}" required placeholder="Ej: Caja Principal">
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Código</label>
                        <input type="text" name="code" class="form-control @error('code') is-invalid @enderror"
                            value="{{ old('code', $caja?->code) }}" placeholder="Ej: CX-01">
                        @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12">
                        <label class="form-label">Sucursal</label>
                        <select name="branch_id" class="form-select @error('branch_id') is-invalid @enderror">
                            <option value="">Sin sucursal asignada</option>
                            @foreach($branches as $branch)
                                <option value="{{ $branch->id }}" {{ (string) old('branch_id', $caja?->branch_id) === (string) $branch->id ? 'selected' : '' }}>
                                    {{ $branch->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('branch_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12">
                        <label class="form-label">Descripción</label>
                        <textarea name="description" rows="2" class="form-control" placeholder="Descripción opcional de la caja...">{{ old('description', $caja?->description) }}</textarea>
                    </div>

                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="active" value="1"
                                {{ old('active', $caja?->active ?? true) ? 'checked' : '' }}>
                            <label class="form-check-label">Caja activa</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button class="btn btn-primary px-4" type="submit">
                <i class="bi bi-save"></i> {{ $caja ? 'Guardar cambios' : 'Crear caja' }}
            </button>
            <a href="{{ route('cajas.index') }}" class="btn btn-light border">Cancelar</a>
        </div>
    </form>
</div>
