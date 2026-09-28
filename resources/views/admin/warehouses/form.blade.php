<div class="card mt-4">
    <div class="card-body">
        <form action="{{ $warehouse ? route('warehouses.update', $warehouse) : route('warehouses.store') }}" method="POST">
            @csrf
            @if($warehouse)
                @method('PUT')
            @endif

            <div class="row g-3">
                <div class="col-md-8">
                    <label for="name" class="form-label">Nombre</label>
                    <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror"
                           value="{{ old('name', $warehouse?->name) }}" required>
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-4">
                    <label class="form-label">Código</label>
                    <input type="text" class="form-control" value="{{ $warehouse?->code ?? 'Se genera al guardar' }}" disabled>
                    <small class="text-muted">Lo asigna el sistema y no se puede cambiar.</small>
                </div>

                <div class="col-md-6">
                    <label for="phone" class="form-label">Teléfono</label>
                    <input type="text" id="phone" name="phone" class="form-control @error('phone') is-invalid @enderror"
                           value="{{ old('phone', $warehouse?->phone) }}">
                    @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6">
                    <label for="address" class="form-label">Dirección</label>
                    <input type="text" id="address" name="address" class="form-control @error('address') is-invalid @enderror"
                           value="{{ old('address', $warehouse?->address) }}">
                    @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12">
                    <label for="description" class="form-label">Descripción</label>
                    <textarea id="description" name="description" rows="2"
                              class="form-control @error('description') is-invalid @enderror">{{ old('description', $warehouse?->description) }}</textarea>
                    @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="active" name="active" value="1"
                               {{ old('active', $warehouse?->active ?? true) ? 'checked' : '' }}>
                        <label class="form-check-label" for="active">Almacén activo</label>
                    </div>
                </div>

                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle"></i> {{ $warehouse ? 'Actualizar' : 'Crear Almacén' }}
                    </button>
                    <a href="{{ route('warehouses.index') }}" class="btn btn-light border">
                        <i class="bi bi-arrow-left"></i> Cancelar
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>
