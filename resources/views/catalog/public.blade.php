@php
    $whatsapp = preg_replace('/\D+/', '', (string) $company->phone);
    $money = fn ($v) => \App\Support\Money::format($v, $company);
@endphp
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $company->name }} · Catálogo</title>
    <meta name="description" content="Servicios{{ $products->isNotEmpty() ? ' y productos' : '' }} de {{ $company->name }}.">
    {{-- Para que al compartir el enlace se vea el nombre y el logo. --}}
    <meta property="og:title" content="{{ $company->name }} · Catálogo">
    <meta property="og:description" content="Servicios y precios de {{ $company->name }}.">
    @if($company->logoUrl())<meta property="og:image" content="{{ $company->logoUrl() }}">@endif
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root { --ink: #0B0C10; --ink-soft: #14161D; --copper: #E64A38; --copper-dark: #B82E1E; --muted: #6C757D; --canvas: #F5F6F8; --border: #E4E6EB; }
        body { background: var(--canvas); color: var(--ink); font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .hero { background: linear-gradient(160deg, var(--ink-soft), var(--ink)); color: #fff; padding: 2.25rem 1rem 3.5rem; }
        .hero-logo { width: 84px; height: 84px; border-radius: 50%; object-fit: cover; background: #fff; border: 3px solid rgba(255,255,255,.15); }
        .hero-initial { width: 84px; height: 84px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center;
                        font-size: 2rem; font-weight: 800; background: linear-gradient(135deg, var(--copper), var(--copper-dark)); }
        .hero h1 { font-weight: 800; letter-spacing: -.5px; }
        .hero .meta { color: #C5C9D3; font-size: .92rem; }
        .content { margin-top: -2rem; }
        .section-title { font-size: .78rem; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: var(--muted); margin: 1.5rem 0 .6rem; }
        .item-card { background: #fff; border: 1px solid var(--border); border-radius: 16px; overflow: hidden; }
        .item { display: flex; justify-content: space-between; gap: 1rem; padding: .9rem 1.1rem; border-bottom: 1px solid var(--border); break-inside: avoid; }
        .item:last-child { border-bottom: 0; }
        .item-name { font-weight: 700; }
        .item-desc { color: var(--muted); font-size: .86rem; }
        .item-price { font-weight: 800; white-space: nowrap; color: var(--ink); }
        .group-name { font-weight: 800; font-size: 1.05rem; margin: 1rem 0 .5rem; }
        .btn-copper { background: var(--copper); border-color: var(--copper); color: #fff; }
        .btn-copper:hover { background: var(--copper-dark); border-color: var(--copper-dark); color: #fff; }
        .actions .btn { border-radius: 999px; }
        footer { color: var(--muted); font-size: .8rem; }

        /* «Descargar PDF»: el navegador lo guarda con este diseño. */
        @media print {
            @page { margin: 14mm; }
            body { background: #fff; }
            .no-print { display: none !important; }
            .hero { background: #fff !important; color: var(--ink); padding: 0 0 1rem; border-bottom: 2px solid var(--ink); }
            .hero .meta { color: var(--muted); }
            .content { margin-top: 0; }
            .item-card { border-radius: 0; border-left: 0; border-right: 0; }
            * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>
    <header class="hero text-center">
        <div class="container" style="max-width: 760px">
            @if($company->logoUrl())
                <img src="{{ $company->logoUrl() }}" alt="{{ $company->name }}" class="hero-logo mb-3">
            @else
                <span class="hero-initial mb-3">{{ mb_substr($company->name, 0, 1) }}</span>
            @endif
            <h1 class="h3 mb-1">{{ $company->name }}</h1>
            <div class="meta">
                @if($company->address)<div><i class="bi bi-geo-alt"></i> {{ $company->address }}</div>@endif
                @if($company->phone)<div><i class="bi bi-telephone"></i> {{ $company->phone }}</div>@endif
            </div>

            <div class="actions d-flex flex-wrap justify-content-center gap-2 mt-3 no-print">
                @if($whatsapp)
                    <a href="https://wa.me/{{ $whatsapp }}?text={{ rawurlencode('Hola, vi su catálogo y quisiera reservar.') }}"
                       class="btn btn-success btn-sm px-3" target="_blank" rel="noopener">
                        <i class="bi bi-whatsapp"></i> Reservar por WhatsApp
                    </a>
                @endif
                <button type="button" class="btn btn-light btn-sm px-3" onclick="window.print()">
                    <i class="bi bi-file-earmark-pdf"></i> Descargar PDF
                </button>
                <button type="button" class="btn btn-outline-light btn-sm px-3" id="share">
                    <i class="bi bi-share"></i> Compartir
                </button>
            </div>
        </div>
    </header>

    <main class="container content pb-5" style="max-width: 760px">
        <div class="section-title">Servicios</div>
        @forelse($services as $category => $items)
            @if($services->count() > 1 || $category !== 'Otros')<div class="group-name">{{ $category }}</div>@endif
            <div class="item-card mb-3">
                @foreach($items as $service)
                    <div class="item">
                        <div>
                            <div class="item-name">{{ $service->name }}</div>
                            @if($service->description)<div class="item-desc">{{ $service->description }}</div>@endif
                            <div class="item-desc"><i class="bi bi-clock"></i> {{ $service->durationLabel() }}</div>
                        </div>
                        <div class="item-price">{{ $money($service->price) }}</div>
                    </div>
                @endforeach
            </div>
        @empty
            <div class="item-card p-4 text-center text-muted">Pronto publicaremos nuestros servicios.</div>
        @endforelse

        @if($products->isNotEmpty())
            <div class="section-title">Productos</div>
            @foreach($products as $category => $items)
                @if($products->count() > 1 || $category !== 'Otros')<div class="group-name">{{ $category }}</div>@endif
                <div class="item-card mb-3">
                    @foreach($items as $product)
                        <div class="item">
                            <div>
                                <div class="item-name">{{ $product->name }}</div>
                                @if($product->description)<div class="item-desc">{{ $product->description }}</div>@endif
                            </div>
                            <div class="item-price">{{ $money($product->sale_price) }}</div>
                        </div>
                    @endforeach
                </div>
            @endforeach
        @endif

        <footer class="text-center mt-4">
            Precios en {{ \App\Support\Money::symbol($company) }} · {{ $company->name }}
            <div class="d-none d-print-block">{{ $url }}</div>
        </footer>
    </main>

    <script>
    // Compartir: el menú nativo del teléfono; si no hay, se copia el enlace.
    document.getElementById('share').addEventListener('click', async function () {
        const data = { title: @json($company->name), text: 'Nuestros servicios y precios', url: @json($url) };
        try {
            if (navigator.share) { await navigator.share(data); return; }
            await navigator.clipboard.writeText(data.url);
            this.innerHTML = '<i class="bi bi-check2"></i> Enlace copiado';
        } catch (e) { /* el usuario cerró el menú */ }
    });
    </script>
</body>
</html>
