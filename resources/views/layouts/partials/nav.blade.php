{{--
    Menú de navegación. Se renderiza dos veces con el mismo $navSections: en la
    barra lateral de escritorio y en el offcanvas móvil. $idPrefix evita que los
    ids de los desplegables choquen entre ambas copias.

    Una sección se abre sola si contiene la ruta activa; el resto recuerda cómo
    la dejó el usuario (ver el script de layouts/app.blade.php).
--}}
@foreach($navSections as $section)
    @php
        $sectionId = $idPrefix.'-'.$section['key'];
        $hasActive = collect($section['items'])->contains(fn ($item) => request()->routeIs($item['pattern']));
    @endphp

    <div class="nav-section" data-section="{{ $section['key'] }}" style="--section-color: {{ $section['color'] }};">
        <button class="nav-section-toggle {{ $hasActive ? '' : 'collapsed' }}"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#{{ $sectionId }}"
                aria-expanded="{{ $hasActive ? 'true' : 'false' }}"
                aria-controls="{{ $sectionId }}">
            <span class="nav-section-dot"></span>
            <span class="nav-section-label">{{ $section['label'] }}</span>
            <i class="bi bi-chevron-down nav-section-chevron"></i>
        </button>

        <div class="collapse {{ $hasActive ? 'show' : '' }}" id="{{ $sectionId }}">
            <ul class="nav flex-column nav-section-items">
                @foreach($section['items'] as $item)
                    <li class="nav-item">
                        <a class="nav-link app-link {{ request()->routeIs($item['pattern']) ? 'active' : '' }}"
                           href="{{ route($item['route']) }}">
                            <i class="bi {{ $item['icon'] }}"></i> <span>{{ $item['label'] }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
@endforeach
