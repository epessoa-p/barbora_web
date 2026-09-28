<div class="card mt-4">
    <div class="card-body">
        <form action="{{ $category ? route('service-categories.update', $category) : route('service-categories.store') }}" method="POST">
            @csrf
            @if($category)
                @method('PUT')
            @endif

            <div class="row g-3">
                <div class="col-md-6">
                    <label for="name" class="form-label">Nombre</label>
                    <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror"
                           value="{{ old('name', $category?->name) }}" placeholder="Corte, Barba, Color…" required>
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-3">
                    <label for="color" class="form-label">Color</label>
                    <input type="color" id="color" name="color"
                           class="form-control form-control-color w-100 @error('color') is-invalid @enderror"
                           value="{{ old('color', $category?->color ?? '#6b7280') }}">
                    @error('color')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <small class="text-muted">Distingue la categoría en el calendario.</small>
                </div>

                <div class="col-md-3">
                    <label for="sort_order" class="form-label">Orden</label>
                    <input type="number" min="0" id="sort_order" name="sort_order" class="form-control"
                           value="{{ old('sort_order', $category?->sort_order ?? 0) }}">
                </div>

                <div class="col-12">
                    <label for="description" class="form-label">Descripción</label>
                    <input type="text" id="description" name="description"
                           class="form-control @error('description') is-invalid @enderror"
                           value="{{ old('description', $category?->description) }}">
                    @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="active" name="active" value="1"
                               {{ old('active', $category?->active ?? true) ? 'checked' : '' }}>
                        <label class="form-check-label" for="active">Categoría activa</label>
                    </div>
                </div>

                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle"></i> {{ $category ? 'Actualizar' : 'Crear categoría' }}
                    </button>
                    <a href="{{ route('service-categories.index') }}" class="btn btn-light border">
                        <i class="bi bi-arrow-left"></i> Cancelar
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>
