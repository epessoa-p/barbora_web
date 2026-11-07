@extends('layouts.app')

@section('title', 'Manual de usuario - Barbora')

{{-- El manual: una sección por módulo, con buscador. Se puede imprimir o guardar en PDF. --}}
@section('page')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h4 fw-bold mb-1"><i class="bi bi-book"></i> Manual de usuario</h1>
        <p class="text-muted mb-0">Cómo usar el sistema. Solo aparece lo que tu plan y tus permisos incluyen.</p>
    </div>
    <button type="button" onclick="window.print()" class="btn btn-outline-secondary d-print-none">
        <i class="bi bi-printer"></i> Imprimir / PDF
    </button>
</div>

<div class="mb-3 d-print-none" style="max-width: 28rem">
    <input type="search" id="manual-search" class="form-control" placeholder="Buscar: caja, comisión, fotos…" aria-label="Buscar en el manual">
</div>
<p class="text-muted d-none" id="manual-empty">No encontramos nada con esa palabra.</p>

<div class="accordion" id="manual">
    @foreach($sections as $i => $section)
        <div class="accordion-item manual-section">
            <h2 class="accordion-header">
                <button class="accordion-button {{ $i === 0 ? '' : 'collapsed' }} fw-semibold" type="button"
                        data-bs-toggle="collapse" data-bs-target="#manual-{{ $section['key'] }}">
                    {{ $section['title'] }}
                </button>
            </h2>
            <div id="manual-{{ $section['key'] }}" class="accordion-collapse collapse {{ $i === 0 ? 'show' : '' }}">
                <div class="accordion-body">
                    <p class="mb-1">{{ $section['intro'] }}</p>
                    @if($section['where'])
                        <p class="small text-muted"><i class="bi bi-signpost-2"></i> Dónde: {{ $section['where'] }}</p>
                    @endif
                    @foreach($section['topics'] as $topic)
                        <div class="manual-topic mb-3">
                            <h3 class="h6 fw-bold mb-2">{{ $topic['title'] }}</h3>
                            <ol class="mb-2 ps-3">
                                @foreach($topic['steps'] as $step)
                                    <li>{{ $step }}</li>
                                @endforeach
                            </ol>
                            @if($topic['note'])
                                <div class="alert alert-light border small py-2 mb-0"><i class="bi bi-info-circle"></i> {{ $topic['note'] }}</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endforeach
</div>

<style>
    @media print {
        .accordion-collapse { display: block !important; height: auto !important; }
        .accordion-button::after { display: none; }
        .manual-section { break-inside: avoid-page; border: 0; }
    }
</style>
@endsection

@push('scripts')
<script>
// Buscador: deja solo las secciones y temas con la palabra, y las abre.
(function () {
    const input = document.getElementById('manual-search');
    const sections = document.querySelectorAll('.manual-section');
    const empty = document.getElementById('manual-empty');
    const clean = (s) => s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');

    input.addEventListener('input', function () {
        const term = clean(this.value.trim());
        let found = 0;

        sections.forEach(function (section) {
            const panel = section.querySelector('.accordion-collapse');
            const inTitle = term !== '' && clean(section.querySelector('.accordion-button').textContent).includes(term);
            let any = false;

            section.querySelectorAll('.manual-topic').forEach(function (topic) {
                const match = term === '' || inTitle || clean(topic.textContent).includes(term);
                topic.classList.toggle('d-none', !match);
                any = any || match;
            });

            section.classList.toggle('d-none', !any);
            if (any) found++;
            if (term !== '') panel.classList.toggle('show', any);
        });

        empty.classList.toggle('d-none', found > 0);
    });
})();
</script>
@endpush
