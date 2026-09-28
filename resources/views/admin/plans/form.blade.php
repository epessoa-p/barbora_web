@php
    $selectedFeatures = old('features', $plan?->features ?? []);
@endphp

<div class="card mt-4">
    <div class="card-body">
        <form action="{{ $plan ? route('plans.update', $plan) : route('plans.store') }}" method="POST">
            @csrf
            @if($plan)
                @method('PUT')
            @endif

            <div class="row g-3">
                <div class="col-md-6">
                    <label for="name" class="form-label">Nombre</label>
                    <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror"
                           value="{{ old('name', $plan?->name) }}" required>
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6">
                    <label for="slug" class="form-label">Slug</label>
                    <input type="text" id="slug" name="slug" class="form-control @error('slug') is-invalid @enderror"
                           value="{{ old('slug', $plan?->slug) }}" required>
                    @error('slug')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12">
                    <label for="description" class="form-label">Descripción</label>
                    <input type="text" id="description" name="description" class="form-control @error('description') is-invalid @enderror"
                           value="{{ old('description', $plan?->description) }}">
                    @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-4">
                    <label for="price" class="form-label">Precio</label>
                    <input type="number" step="0.01" min="0" id="price" name="price"
                           class="form-control @error('price') is-invalid @enderror"
                           value="{{ old('price', $plan?->price ?? 0) }}" required>
                    @error('price')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-4">
                    <label for="billing_period" class="form-label">Periodo de cobro</label>
                    <select id="billing_period" name="billing_period" class="form-select" required>
                        <option value="monthly" {{ old('billing_period', $plan?->billing_period) === 'monthly' ? 'selected' : '' }}>Mensual</option>
                        <option value="yearly" {{ old('billing_period', $plan?->billing_period) === 'yearly' ? 'selected' : '' }}>Anual</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label for="trial_days" class="form-label">Días de prueba</label>
                    <input type="number" min="0" max="365" id="trial_days" name="trial_days"
                           class="form-control @error('trial_days') is-invalid @enderror"
                           value="{{ old('trial_days', $plan?->trial_days ?? 14) }}" required>
                    @error('trial_days')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12">
                    <hr class="my-2">
                    <h6 class="fw-bold mb-1">Límites</h6>
                    <p class="text-muted small mb-3">Deja el campo vacío para que el recurso sea ilimitado.</p>
                </div>

                <div class="col-md-4">
                    <label for="max_users" class="form-label">Máximo de usuarios</label>
                    <input type="number" min="0" id="max_users" name="max_users" class="form-control"
                           value="{{ old('max_users', $plan?->max_users) }}" placeholder="Ilimitado">
                </div>

                <div class="col-md-4">
                    <label for="max_branches" class="form-label">Máximo de sucursales</label>
                    <input type="number" min="0" id="max_branches" name="max_branches" class="form-control"
                           value="{{ old('max_branches', $plan?->max_branches) }}" placeholder="Ilimitado">
                </div>

                <div class="col-md-4">
                    <label for="max_products" class="form-label">Máximo de productos</label>
                    <input type="number" min="0" id="max_products" name="max_products" class="form-control"
                           value="{{ old('max_products', $plan?->max_products) }}" placeholder="Ilimitado">
                </div>

                <div class="col-12">
                    <hr class="my-2">
                    <h6 class="fw-bold mb-1">Módulos incluidos</h6>
                    <p class="text-muted small mb-3">
                        Los módulos administrativos (empresa, usuarios, roles, cargos, personal,
                        sucursales y cajas) están siempre disponibles y no dependen del plan.
                    </p>
                    <div class="row g-2">
                        @foreach(\App\Models\Plan::MODULES as $slug => $label)
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="features[]"
                                           value="{{ $slug }}" id="feature-{{ $slug }}"
                                           {{ in_array($slug, $selectedFeatures, true) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="feature-{{ $slug }}">{{ $label }}</label>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="col-md-4">
                    <label for="sort_order" class="form-label">Orden en la lista</label>
                    <input type="number" min="0" id="sort_order" name="sort_order" class="form-control"
                           value="{{ old('sort_order', $plan?->sort_order ?? 0) }}">
                </div>

                <div class="col-12">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="active" name="active" value="1"
                               {{ old('active', $plan?->active ?? true) ? 'checked' : '' }}>
                        <label class="form-check-label" for="active">Plan activo (se ofrece a empresas nuevas)</label>
                    </div>
                </div>

                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle"></i> {{ $plan ? 'Actualizar' : 'Crear Plan' }}
                    </button>
                    <a href="{{ route('plans.index') }}" class="btn btn-light border">
                        <i class="bi bi-arrow-left"></i> Cancelar
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>
