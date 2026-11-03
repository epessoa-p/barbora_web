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
        .item-photo { width: 64px; height: 64px; border-radius: 12px; object-fit: cover; flex-shrink: 0; background: var(--canvas); }
        .item-desc { color: var(--muted); font-size: .86rem; }
        .item-price { font-weight: 800; white-space: nowrap; color: var(--ink); }
        .group-name { font-weight: 800; font-size: 1.05rem; margin: 1rem 0 .5rem; }
        .btn-copper { background: var(--copper); border-color: var(--copper); color: #fff; }
        .btn-copper:hover { background: var(--copper-dark); border-color: var(--copper-dark); color: #fff; }
        .actions .btn { border-radius: 999px; }
        footer { color: var(--muted); font-size: .8rem; }

        /* Los cortes de cada servicio: una fila que se desliza, y el visor. */
        .item { flex-wrap: wrap; }
        .cuts { flex-basis: 100%; display: flex; gap: .5rem; overflow-x: auto; scroll-snap-type: x mandatory; padding-bottom: .25rem; -webkit-overflow-scrolling: touch; }
        .cut { flex: 0 0 auto; width: 92px; padding: 0; border: 0; background: none; text-align: left; scroll-snap-align: start; }
        .cut img { width: 92px; height: 92px; border-radius: 12px; object-fit: cover; background: var(--canvas); display: block; }
        .cut span { display: block; font-size: .75rem; color: var(--muted); margin-top: .2rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .viewer { position: fixed; inset: 0; background: rgba(0,0,0,.92); z-index: 1050; display: none; flex-direction: column; align-items: center; justify-content: center; padding: 1rem; color: #fff; }
        .viewer.open { display: flex; }
        .viewer img { max-width: 100%; max-height: 72vh; border-radius: 12px; object-fit: contain; }
        .viewer .caption { margin-top: .8rem; font-weight: 600; text-align: center; }
        .viewer .counter { color: rgba(255,255,255,.6); font-size: .85rem; }
        .viewer button { background: rgba(255,255,255,.12); border: 0; color: #fff; border-radius: 50%; width: 44px; height: 44px; font-size: 1.3rem; }
        .viewer .close { position: absolute; top: 1rem; right: 1rem; }
        .viewer .nav { display: flex; gap: 1.5rem; margin-top: 1rem; }

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
                        <div class="d-flex gap-3 align-items-center">
                            @if($service->photoUrl())
                                <img src="{{ $service->photoUrl() }}" alt="{{ $service->name }}" class="item-photo" loading="lazy">
                            @endif
                            <div>
                                <div class="item-name">{{ $service->name }}</div>
                                @if($service->description)<div class="item-desc">{{ $service->description }}</div>@endif
                                <div class="item-desc"><i class="bi bi-clock"></i> {{ $service->durationLabel() }}</div>
                            </div>
                        </div>
                        <div class="item-price">{{ $money($service->price) }}</div>

                        {{-- Los cortes del servicio: se deslizan; al tocar, se ven en grande. --}}
                        @if($service->photos->count() > 1 || $service->photos->first()?->caption)
                            <div class="cuts no-print">
                                @foreach($service->photos as $photo)
                                    <button type="button" class="cut" data-group="s{{ $service->id }}"
                                            data-src="{{ $photo->url() }}" data-caption="{{ $photo->caption }}" data-title="{{ $service->name }}">
                                        <img src="{{ $photo->url() }}" alt="{{ $photo->caption ?: $service->name }}" loading="lazy">
                                        @if($photo->caption)<span>{{ $photo->caption }}</span>@endif
                                    </button>
                                @endforeach
                            </div>
                        @endif
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
                            <div class="d-flex gap-3 align-items-center">
                                @if($product->photoUrl())
                                    <img src="{{ $product->photoUrl() }}" alt="{{ $product->name }}" class="item-photo" loading="lazy">
                                @endif
                                <div>
                                    <div class="item-name">{{ $product->name }}</div>
                                    @if($product->description)<div class="item-desc">{{ $product->description }}</div>@endif
                                </div>
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

    <div class="viewer no-print" id="viewer" role="dialog" aria-modal="true">
        <button type="button" class="close" aria-label="Cerrar"><i class="bi bi-x-lg"></i></button>
        <div class="counter mb-2"></div>
        <img alt="">
        <div class="caption"></div>
        <div class="nav">
            <button type="button" class="prev" aria-label="Anterior"><i class="bi bi-chevron-left"></i></button>
            <button type="button" class="next" aria-label="Siguiente"><i class="bi bi-chevron-right"></i></button>
        </div>
    </div>

    <script>
    // Visor de los cortes: la foto en grande con su comentario; las flechas
    // (o deslizar) pasan entre las fotos del mismo servicio.
    (function () {
        const viewer = document.getElementById('viewer');
        const img = viewer.querySelector('img');
        let list = [], index = 0, startX = null;

        const show = () => {
            const cut = list[index];
            img.src = cut.dataset.src;
            viewer.querySelector('.caption').textContent = cut.dataset.caption || cut.dataset.title;
            viewer.querySelector('.counter').textContent = list.length > 1 ? (index + 1) + ' / ' + list.length : '';
            viewer.querySelector('.nav').style.visibility = list.length > 1 ? 'visible' : 'hidden';
        };
        const move = step => { index = (index + step + list.length) % list.length; show(); };
        const close = () => viewer.classList.remove('open');

        document.querySelectorAll('.cut').forEach(cut => cut.addEventListener('click', () => {
            list = Array.from(document.querySelectorAll('.cut[data-group="' + cut.dataset.group + '"]'));
            index = list.indexOf(cut);
            show();
            viewer.classList.add('open');
        }));
        viewer.querySelector('.close').addEventListener('click', close);
        viewer.querySelector('.prev').addEventListener('click', () => move(-1));
        viewer.querySelector('.next').addEventListener('click', () => move(1));
        viewer.addEventListener('click', e => { if (e.target === viewer) close(); });
        document.addEventListener('keydown', e => {
            if (!viewer.classList.contains('open')) return;
            if (e.key === 'Escape') close();
            if (e.key === 'ArrowLeft') move(-1);
            if (e.key === 'ArrowRight') move(1);
        });
        viewer.addEventListener('touchstart', e => { startX = e.touches[0].clientX; }, { passive: true });
        viewer.addEventListener('touchend', e => {
            if (startX === null) return;
            const dx = e.changedTouches[0].clientX - startX;
            if (Math.abs(dx) > 40) move(dx < 0 ? 1 : -1);
            startX = null;
        });
    })();

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
