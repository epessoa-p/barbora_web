<div class="card mt-4">
    <div class="card-body">
        <form action="{{ $client ? route('clients.update', $client) : route('clients.store') }}" method="POST">
            @csrf
            @if($client)
                @method('PUT')
            @endif

            <div class="row g-3">
                <div class="col-md-6">
                    <label for="full_name" class="form-label">Nombre completo</label>
                    <input type="text" id="full_name" name="full_name"
                           class="form-control @error('full_name') is-invalid @enderror"
                           value="{{ old('full_name', $client?->full_name) }}" required autofocus>
                    @error('full_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-3">
                    <label for="phone" class="form-label">Teléfono</label>
                    <input type="text" id="phone" name="phone" class="form-control @error('phone') is-invalid @enderror"
                           value="{{ old('phone', $client?->phone) }}" placeholder="+591 7 000-0000">
                    @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-3">
                    <label for="document_number" class="form-label">Documento</label>
                    <input type="text" id="document_number" name="document_number"
                           class="form-control @error('document_number') is-invalid @enderror"
                           value="{{ old('document_number', $client?->document_number) }}">
                    @error('document_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6">
                    <label for="email" class="form-label">Correo</label>
                    <input type="email" id="email" name="email" class="form-control @error('email') is-invalid @enderror"
                           value="{{ old('email', $client?->email) }}">
                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6">
                    <label for="birth_date" class="form-label">Fecha de nacimiento</label>
                    <input type="date" id="birth_date" name="birth_date"
                           class="form-control @error('birth_date') is-invalid @enderror"
                           value="{{ old('birth_date', $client?->birth_date?->format('Y-m-d')) }}">
                    @error('birth_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <small class="text-muted">Para saludos y promociones de cumpleaños.</small>
                </div>

                <div class="col-12"><hr class="my-2"></div>

                <div class="col-md-6">
                    <label for="preferences" class="form-label">Preferencias</label>
                    <input type="text" id="preferences" name="preferences"
                           class="form-control @error('preferences') is-invalid @enderror"
                           value="{{ old('preferences', $client?->preferences) }}"
                           placeholder="Máquina 2 a los costados, tijera arriba">
                    @error('preferences')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6">
                    <label for="allergies" class="form-label">
                        Alergias <i class="bi bi-exclamation-triangle text-warning"></i>
                    </label>
                    <input type="text" id="allergies" name="allergies"
                           class="form-control @error('allergies') is-invalid @enderror"
                           value="{{ old('allergies', $client?->allergies) }}"
                           placeholder="Tintes con amoníaco, látex…">
                    @error('allergies')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <small class="text-muted">Se muestra destacado en la ficha antes de atender.</small>
                </div>

                <div class="col-12">
                    <label for="notes" class="form-label">Notas</label>
                    <textarea id="notes" name="notes" rows="3"
                              class="form-control @error('notes') is-invalid @enderror">{{ old('notes', $client?->notes) }}</textarea>
                    @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="active" name="active" value="1"
                               {{ old('active', $client?->active ?? true) ? 'checked' : '' }}>
                        <label class="form-check-label" for="active">Cliente activo</label>
                    </div>
                </div>

                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle"></i> {{ $client ? 'Actualizar' : 'Registrar cliente' }}
                    </button>
                    <a href="{{ route('clients.index') }}" class="btn btn-light border">
                        <i class="bi bi-arrow-left"></i> Cancelar
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>
