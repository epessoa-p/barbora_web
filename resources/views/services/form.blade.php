<div class="card mt-4">
    <div class="card-body">
        <form action="{{ $service ? route('services.update', $service) : route('services.store') }}" method="POST">
            @csrf
            @if($service)
                @method('PUT')
            @endif

            <div class="row g-3">
                <div class="col-md-7">
                    <label for="name" class="form-label">Nombre del servicio</label>
                    <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror"
                           value="{{ old('name', $service?->name) }}" placeholder="Corte clásico" required>
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-5">
                    <label for="service_category_id" class="form-label">Categoría</label>
                    <select id="service_category_id" name="service_category_id"
                            class="form-select @error('service_category_id') is-invalid @enderror">
                        <option value="">Sin categoría</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}"
                                    {{ (string) old('service_category_id', $service?->service_category_id) === (string) $category->id ? 'selected' : '' }}>
                                {{ $category->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('service_category_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    @if($categories->isEmpty())
                        <small class="text-muted">
                            Todavía no hay categorías. <a href="{{ route('service-categories.create') }}">Crea la primera</a>.
                        </small>
                    @endif
                </div>

                <div class="col-12">
                    <label for="description" class="form-label">Descripción</label>
                    <input type="text" id="description" name="description"
                           class="form-control @error('description') is-invalid @enderror"
                           value="{{ old('description', $service?->description) }}"
                           placeholder="Qué incluye, para que el cliente sepa qué esperar">
                    @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-4">
                    <label for="duration_minutes" class="form-label">Duración</label>
                    <div class="input-group">
                        <input type="number" min="5" max="600" step="5" id="duration_minutes" name="duration_minutes"
                               class="form-control @error('duration_minutes') is-invalid @enderror"
                               value="{{ old('duration_minutes', $service?->duration_minutes ?? 30) }}" required>
                        <span class="input-group-text">min</span>
                        @error('duration_minutes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <small class="text-muted">Es el hueco que reservará en la agenda.</small>
                </div>

                <div class="col-md-4">
                    <label for="price" class="form-label">Precio</label>
                    <div class="input-group">
                        <span class="input-group-text">{{ \App\Support\Money::symbol($tenantCompany) }}</span>
                        <input type="number" min="0" step="0.01" id="price" name="price"
                               class="form-control @error('price') is-invalid @enderror"
                               value="{{ old('price', $service?->price ?? 0) }}" required>
                        @error('price')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="col-md-4">
                    <label for="sort_order" class="form-label">Orden en la lista</label>
                    <input type="number" min="0" id="sort_order" name="sort_order" class="form-control"
                           value="{{ old('sort_order', $service?->sort_order ?? 0) }}">
                </div>

                <div class="col-12">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="active" name="active" value="1"
                               {{ old('active', $service?->active ?? true) ? 'checked' : '' }}>
                        <label class="form-check-label" for="active">Servicio activo (se puede agendar y cobrar)</label>
                    </div>
                </div>

                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle"></i> {{ $service ? 'Actualizar' : 'Crear servicio' }}
                    </button>
                    <a href="{{ route('services.index') }}" class="btn btn-light border">
                        <i class="bi bi-arrow-left"></i> Cancelar
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>
