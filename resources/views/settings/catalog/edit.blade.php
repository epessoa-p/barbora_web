@extends('layouts.app')

@section('title', 'Catálogo - Barbora')

@php
    $canEdit = auth()->user()->hasPermissionInCompany('settings.edit', $tenantCompany);
@endphp

@section('page')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h4 fw-bold mb-1"><i class="bi bi-qr-code"></i> Catálogo</h1>
        <p class="text-muted mb-0">Una página pública con tus servicios y precios, para compartir o poner en la puerta con un QR.</p>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <form action="{{ route('catalog-settings.update') }}" method="POST" class="card border-0 shadow-sm mb-4">
            @csrf
            @method('PUT')
            <div class="card-body p-4">
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" role="switch" id="catalog_enabled" name="catalog_enabled"
                           value="1" {{ $company->catalog_enabled ? 'checked' : '' }} {{ $canEdit ? '' : 'disabled' }}>
                    <label class="form-check-label fw-semibold" for="catalog_enabled">Publicar el catálogo</label>
                    <div class="small text-muted">
                        Apagado, el enlace y el QR no muestran nada. Encendido, cualquiera con el enlace lo ve.
                    </div>
                </div>

                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" role="switch" id="catalog_show_products" name="catalog_show_products"
                           value="1" {{ $company->catalog_show_products ? 'checked' : '' }} {{ $canEdit ? '' : 'disabled' }}>
                    <label class="form-check-label fw-semibold" for="catalog_show_products">Mostrar también los productos</label>
                    <div class="small text-muted">
                        Los servicios salen siempre. Los productos a la venta, solo si lo marcas.
                    </div>
                </div>

                <p class="small text-muted mb-3">
                    Se muestran los {{ $servicesCount }} servicios activos, por categoría, con duración y precio.
                    Lo que cambies en servicios y productos se ve al instante en el catálogo.
                </p>

                @if($canEdit)
                    <button class="btn btn-primary"><i class="bi bi-save"></i> Guardar</button>
                @endif
            </div>
        </form>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-2"><i class="bi bi-link-45deg"></i> Enlace</h6>
                <div class="input-group mb-2">
                    <input type="text" class="form-control" id="catalog-url" value="{{ $url }}" readonly>
                    <button class="btn btn-outline-secondary" type="button" id="copy-url"><i class="bi bi-clipboard"></i> Copiar</button>
                </div>
                @unless($company->catalog_enabled)
                    <div class="alert alert-warning small py-2 mb-2">
                        El catálogo no está publicado: el enlace todavía no muestra nada.
                    </div>
                @endunless
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ $url }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary {{ $company->catalog_enabled ? '' : 'disabled' }}">
                        <i class="bi bi-box-arrow-up-right"></i> Ver catálogo
                    </a>
                    <a href="{{ $posterUrl }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary {{ $company->catalog_enabled ? '' : 'disabled' }}">
                        <i class="bi bi-printer"></i> Afiche con QR (imprimir / PDF)
                    </a>
                </div>

                @if($canEdit)
                    <hr>
                    <form action="{{ route('catalog-settings.regenerate') }}" method="POST"
                          onsubmit="return confirm('¿Generar un enlace nuevo? El actual y los QR ya impresos dejarán de funcionar.')">
                        @csrf
                        <button class="btn btn-sm btn-link text-danger px-0"><i class="bi bi-arrow-repeat"></i> Generar un enlace nuevo</button>
                        <div class="small text-muted">Úsalo si quieres invalidar el enlace actual.</div>
                    </form>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card border-0 shadow-sm text-center">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-3">Código QR</h6>
                <div id="qr" class="mx-auto mb-3" style="max-width: 240px"></div>
                <button type="button" class="btn btn-sm btn-outline-dark" id="download-qr">
                    <i class="bi bi-download"></i> Descargar QR (imagen)
                </button>
                <p class="small text-muted mt-3 mb-0">
                    Para la puerta o el mostrador, mejor el <strong>afiche</strong>: lleva tu logo, el nombre y el QR en grande.
                </p>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcode-generator/1.4.4/qrcode.min.js"></script>
<script>
(function () {
    const url = @json($url);
    const qr = qrcode(0, 'M');
    qr.addData(url);
    qr.make();
    document.getElementById('qr').innerHTML = qr.createSvgTag({ cellSize: 6, margin: 2, scalable: true });

    document.getElementById('download-qr').addEventListener('click', function () {
        const cell = 20, margin = 2, n = qr.getModuleCount();
        const canvas = document.createElement('canvas');
        canvas.width = canvas.height = (n + margin * 2) * cell;
        const ctx = canvas.getContext('2d');
        ctx.fillStyle = '#fff';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.fillStyle = '#000';
        for (let r = 0; r < n; r++) for (let c = 0; c < n; c++) {
            if (qr.isDark(r, c)) ctx.fillRect((c + margin) * cell, (r + margin) * cell, cell, cell);
        }
        const link = document.createElement('a');
        link.download = 'qr-catalogo.png';
        link.href = canvas.toDataURL('image/png');
        link.click();
    });

    document.getElementById('copy-url').addEventListener('click', async function () {
        try {
            await navigator.clipboard.writeText(url);
        } catch (e) {
            document.getElementById('catalog-url').select();
            document.execCommand('copy');
        }
        this.innerHTML = '<i class="bi bi-check2"></i> Copiado';
    });
})();
</script>
@endpush
