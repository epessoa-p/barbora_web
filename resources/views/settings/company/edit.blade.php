@extends('layouts.app')

@section('title', 'Datos de la barbería - Barbora')

@php
    $canEdit = auth()->user()->hasPermissionInCompany('settings.edit', $tenantCompany);
@endphp

@section('page')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h4 fw-bold mb-1"><i class="bi bi-shop"></i> Datos de la barbería</h1>
        <p class="text-muted mb-0">Lo que ven tus clientes en el comprobante.</p>
    </div>
</div>

@if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0 ps-3">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
@endif

<div class="row g-4">
    <div class="col-lg-7">
        <form action="{{ route('company-profile.update') }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            {{-- El nombre lo edita la barbería; el NIT no (verificación de soporte). --}}
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-shop"></i> Identificación</h6>
                    <div class="row g-3">
                        <div class="col-md-7">
                            <label class="form-label">Nombre de la barbería <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                                   data-preview="name" value="{{ old('name', $company->name) }}" {{ $canEdit ? '' : 'disabled' }} required>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">{{ $company->tax_id_label }}</label>
                            <input type="text" class="form-control" value="{{ $company->tax_id ?? '—' }}" disabled>
                        </div>
                    </div>
                    <small class="text-muted d-block mt-2">
                        Para cambiar el {{ $company->tax_id_label }}, escribe a soporte:
                        es el dato con el que verificamos que eres tú.
                    </small>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-image"></i> Logo</h6>

                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="border rounded-3 d-flex align-items-center justify-content-center bg-light"
                             style="width: 96px; height: 96px; overflow: hidden;">
                            @if($company->logoUrl())
                                <img src="{{ $company->logoUrl() }}" alt="Logo" id="logo-current"
                                     style="max-width: 100%; max-height: 100%; object-fit: contain;">
                            @else
                                <i class="bi bi-image text-muted fs-2" id="logo-empty"></i>
                            @endif
                        </div>
                        <div class="flex-grow-1">
                            <input type="file" name="logo" id="logo" accept="image/png,image/jpeg,image/webp"
                                   class="form-control" {{ $canEdit ? '' : 'disabled' }}>
                            <small class="text-muted">PNG, JPG o WEBP, hasta 1 MB. Mejor con fondo blanco o transparente.</small>
                            @if($company->logo && $canEdit)
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" name="remove_logo" value="1" id="remove_logo">
                                    <label class="form-check-label small" for="remove_logo">Quitar el logo</label>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-geo-alt"></i> Contacto</h6>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Dirección</label>
                            <input type="text" name="address" class="form-control" data-preview="address"
                                   value="{{ old('address', $company->address) }}" {{ $canEdit ? '' : 'disabled' }}>
                            <small class="text-muted">Si la venta es de una sucursal, sale la dirección de la sucursal.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Teléfono</label>
                            <input type="text" name="phone" class="form-control" data-preview="phone"
                                   value="{{ old('phone', $company->phone) }}" {{ $canEdit ? '' : 'disabled' }}>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Correo</label>
                            <input type="email" name="email" class="form-control"
                                   value="{{ old('email', $company->email) }}" {{ $canEdit ? '' : 'disabled' }}>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-receipt"></i> Pie del comprobante</h6>
                    <input type="text" name="receipt_footer" class="form-control" maxlength="255" data-preview="footer"
                           value="{{ old('receipt_footer', $company->receipt_footer) }}"
                           placeholder="¡Gracias por tu visita!" {{ $canEdit ? '' : 'disabled' }}>
                    <small class="text-muted">Tus redes, el horario o una promoción. Vacío, sale «¡Gracias por tu visita!».</small>
                </div>
            </div>

            @if($canEdit)
                <button class="btn btn-primary px-4" type="submit">
                    <i class="bi bi-save"></i> Guardar
                </button>
            @endif
        </form>
    </div>

    {{-- Vista previa: se actualiza mientras se escribe. --}}
    <div class="col-lg-5">
        <div class="position-sticky" style="top: 1rem;">
            <p class="text-muted small fw-semibold text-uppercase mb-2">Así se verá</p>
            <div class="receipt-preview">
                <div class="text-center">
                    <img id="preview-logo" src="{{ $company->logoUrl() }}" alt=""
                         class="{{ $company->logoUrl() ? '' : 'd-none' }}"
                         style="max-height: 56px; max-width: 70%; object-fit: contain; margin-bottom: .4rem;">
                    <div class="fw-bold" style="letter-spacing: .08em;" id="preview-name">{{ Str::upper($company->name) }}</div>
                    @if($company->tax_id)
                        <div class="text-muted">{{ $company->tax_id_label }}: {{ $company->tax_id }}</div>
                    @endif
                    <div class="text-muted" id="preview-address">{{ $company->address }}</div>
                </div>
                <div class="receipt-divider"></div>
                <div class="d-flex justify-content-between"><span class="text-muted">Comprobante</span><strong>V-000123</strong></div>
                <div class="d-flex justify-content-between"><span class="text-muted">Cliente</span><span>Carlos Mendoza</span></div>
                <div class="receipt-divider"></div>
                <div class="d-flex justify-content-between"><span>Corte clásico</span><span>{{ \App\Support\Money::format(50, $company) }}</span></div>
                <div class="d-flex justify-content-between"><span>Barba</span><span>{{ \App\Support\Money::format(20, $company) }}</span></div>
                <div class="receipt-divider"></div>
                <div class="d-flex justify-content-between fw-bold"><span>TOTAL</span><span>{{ \App\Support\Money::format(70, $company) }}</span></div>
                <div class="receipt-divider"></div>
                <div class="text-center text-muted">
                    <div id="preview-footer">{{ $company->receiptFooter() }}</div>
                    <div id="preview-phone">{{ $company->phone }}</div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .receipt-preview {
        width: 80mm; max-width: 100%;
        background: #fff; padding: 1rem 1.1rem;
        font-size: 12px; line-height: 1.45;
        box-shadow: 0 2px 14px rgba(0,0,0,.08);
        border-radius: .25rem;
        font-family: 'Segoe UI', Tahoma, sans-serif;
    }
    .receipt-divider { border-top: 1px dashed #d1d5db; margin: .5rem 0; }
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const fallbackFooter = '¡Gracias por tu visita!';

    document.querySelectorAll('[data-preview]').forEach(function (input) {
        const target = document.getElementById('preview-' + input.dataset.preview);
        if (!target) return;

        input.addEventListener('input', function () {
            const value = input.value.trim();
            target.textContent = input.dataset.preview === 'footer' && value === '' ? fallbackFooter : value;
        });
    });

    // El logo se previsualiza antes de subirlo.
    const file = document.getElementById('logo');
    const preview = document.getElementById('preview-logo');
    const remove = document.getElementById('remove_logo');

    file?.addEventListener('change', function () {
        const f = file.files && file.files[0];
        if (!f) return;
        preview.src = URL.createObjectURL(f);
        preview.classList.remove('d-none');
        if (remove) remove.checked = false;
    });

    remove?.addEventListener('change', function () {
        preview.classList.toggle('d-none', remove.checked);
    });
});
</script>
@endpush
