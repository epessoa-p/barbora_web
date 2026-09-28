<div class="card mt-4">
    <div class="card-body">
        <form action="{{ $category ? route('product-categories.update', $category) : route('product-categories.store') }}" method="POST">
            @csrf
            @if($category) @method('PUT') @endif

            <div class="row g-3">
                <div class="col-md-8">
                    <label for="name" class="form-label">Nombre</label>
                    <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror"
                           value="{{ old('name', $category?->name) }}" placeholder="Ceras, Tintes, Cuidado…" required>
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-4">
                    <label for="sort_order" class="form-label">Orden</label>
                    <input type="number" min="0" id="sort_order" name="sort_order" class="form-control"
                           value="{{ old('sort_order', $category?->sort_order ?? 0) }}">
                </div>

                <div class="col-12">
                    <label for="description" class="form-label">Descripción</label>
                    <input type="text" id="description" name="description" class="form-control"
                           value="{{ old('description', $category?->description) }}">
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
                    <a href="{{ route('product-categories.index') }}" class="btn btn-light border">
                        <i class="bi bi-arrow-left"></i> Cancelar
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>
