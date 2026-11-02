<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $company->name }} · Afiche con QR</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    {{-- El QR se dibuja en el navegador: no hace falta ninguna librería en el servidor. --}}
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcode-generator/1.4.4/qrcode.min.js"></script>
    <style>
        :root { --ink: #0B0C10; --copper: #E64A38; --copper-dark: #B82E1E; --muted: #6C757D; }
        body { background: #EEF0F3; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: var(--ink); }
        .sheet { background: #fff; max-width: 560px; margin: 1.5rem auto; padding: 2.5rem 2rem; border-radius: 20px;
                 box-shadow: 0 10px 40px rgba(0,0,0,.08); text-align: center; }
        .logo { width: 96px; height: 96px; border-radius: 50%; object-fit: cover; }
        .initial { width: 96px; height: 96px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center;
                   font-size: 2.4rem; font-weight: 800; color: #fff; background: linear-gradient(135deg, var(--copper), var(--copper-dark)); }
        h1 { font-weight: 800; letter-spacing: -.5px; }
        .lead-text { font-size: 1.25rem; font-weight: 700; }
        .lead-text span { color: var(--copper); }
        #qr svg { width: 100%; max-width: 320px; height: auto; }
        .qr-frame { display: inline-block; padding: 14px; border: 4px solid var(--ink); border-radius: 22px; margin: 1.25rem 0 .75rem; }
        .hint { color: var(--muted); }
        .url { font-family: monospace; font-size: .85rem; color: var(--muted); word-break: break-all; }
        .btn { border-radius: 999px; }

        /* Imprimir / guardar como PDF: el afiche solo, en una hoja. */
        @media print {
            @page { size: A4 portrait; margin: 12mm; }
            body { background: #fff; }
            .no-print { display: none !important; }
            .sheet { box-shadow: none; max-width: none; margin: 0; padding: 10mm 0; }
            .logo, .initial { width: 130px; height: 130px; }
            h1 { font-size: 2.6rem; }
            .lead-text { font-size: 1.8rem; }
            #qr svg { max-width: 420px; }
            * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>
    <div class="text-center mt-3 no-print d-flex flex-wrap justify-content-center gap-2 px-3">
        <button type="button" class="btn btn-dark btn-sm px-3" onclick="window.print()">
            <i class="bi bi-printer"></i> Imprimir / Guardar PDF
        </button>
        <button type="button" class="btn btn-outline-dark btn-sm px-3" id="download-qr">
            <i class="bi bi-qr-code"></i> Descargar QR (imagen)
        </button>
        <a href="{{ $url }}" class="btn btn-outline-secondary btn-sm px-3" target="_blank" rel="noopener">
            <i class="bi bi-box-arrow-up-right"></i> Ver catálogo
        </a>
    </div>

    <div class="sheet">
        @if($company->logoUrl())
            <img src="{{ $company->logoUrl() }}" alt="" class="logo mb-3">
        @else
            <span class="initial mb-3">{{ mb_substr($company->name, 0, 1) }}</span>
        @endif
        <h1 class="mb-1">{{ $company->name }}</h1>
        @if($company->address)<div class="hint mb-3">{{ $company->address }}</div>@endif

        <div class="lead-text">Mira nuestros <span>servicios y precios</span></div>

        <div class="qr-frame"><div id="qr"></div></div>

        <div class="hint"><i class="bi bi-camera"></i> Escanea con la cámara de tu celular</div>
        <div class="url mt-2">{{ $url }}</div>
    </div>

    <script>
    (function () {
        const url = @json($url);
        const qr = qrcode(0, 'M');
        qr.addData(url);
        qr.make();

        document.getElementById('qr').innerHTML = qr.createSvgTag({ cellSize: 8, margin: 0, scalable: true });

        // PNG grande y nítido, para pegarlo en un diseño propio o en redes.
        document.getElementById('download-qr').addEventListener('click', function () {
            const cell = 20, margin = 2;
            const size = (qr.getModuleCount() + margin * 2) * cell;
            const canvas = document.createElement('canvas');
            canvas.width = canvas.height = size;
            const ctx = canvas.getContext('2d');
            ctx.fillStyle = '#fff';
            ctx.fillRect(0, 0, size, size);
            ctx.fillStyle = '#000';
            for (let r = 0; r < qr.getModuleCount(); r++) {
                for (let c = 0; c < qr.getModuleCount(); c++) {
                    if (qr.isDark(r, c)) ctx.fillRect((c + margin) * cell, (r + margin) * cell, cell, cell);
                }
            }
            const link = document.createElement('a');
            link.download = 'qr-catalogo.png';
            link.href = canvas.toDataURL('image/png');
            link.click();
        });
    })();
    </script>
</body>
</html>
