@if($errors->any())
    <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif

<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <div class="row g-3">
            <div class="col-md-8">
                <label class="form-label">Nombre o razón social <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control" maxlength="255" required value="{{ old('name', $supplier?->name) }}">
            </div>
            <div class="col-md-4">
                <label class="form-label">NIT</label>
                <input type="text" name="tax_id" class="form-control" maxlength="30" value="{{ old('tax_id', $supplier?->tax_id) }}">
            </div>
            <div class="col-md-6">
                <label class="form-label">Persona de contacto</label>
                <input type="text" name="contact_name" class="form-control" maxlength="255" value="{{ old('contact_name', $supplier?->contact_name) }}">
            </div>
            <div class="col-md-6">
                <label class="form-label">Teléfono</label>
                <input type="text" name="phone" class="form-control" maxlength="30" value="{{ old('phone', $supplier?->phone) }}">
            </div>
            <div class="col-md-6">
                <label class="form-label">Correo</label>
                <input type="email" name="email" class="form-control" value="{{ old('email', $supplier?->email) }}">
            </div>
            <div class="col-md-6">
                <label class="form-label">Dirección</label>
                <input type="text" name="address" class="form-control" maxlength="255" data-no-upper value="{{ old('address', $supplier?->address) }}">
            </div>
            <div class="col-12">
                <label class="form-label">Notas</label>
                <textarea name="notes" rows="2" class="form-control" placeholder="Qué vende, días de entrega, condiciones de pago…">{{ old('notes', $supplier?->notes) }}</textarea>
            </div>
            @if($supplier)
                <div class="col-12">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="active" value="1" id="active" {{ old('active', $supplier->active) ? 'checked' : '' }}>
                        <label class="form-check-label" for="active">Activo</label>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
