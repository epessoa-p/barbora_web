{{--
    Marca del encabezado del menú: la identidad de la EMPRESA activa.
      - Con logo  → el logo de la barbería.
      - Sin logo  → un icono genérico.
      - Sin empresa (superadmin / Modo Global) → la marca Barbora.
    $titleId (opcional) pone un id en el título, para aria-labelledby en móvil.
--}}
@if($currentCompany?->logoUrl())
    <img src="{{ $currentCompany->logoUrl() }}" alt="{{ $currentCompany->name }}" class="brand-icon">
@elseif($currentCompany)
    <span class="brand-icon d-inline-flex align-items-center justify-content-center bg-white text-dark">
        <i class="bi bi-shop" style="font-size: 1.25rem;"></i>
    </span>
@else
    <img src="{{ asset('img/logo.svg') }}" alt="Barbora" class="brand-icon">
@endif
<div>
    <div class="brand-title" @isset($titleId) id="{{ $titleId }}" @endisset>{{ $currentCompany?->name ?? 'BARBORA' }}</div>
    @unless($currentCompany)
        <small class="text-muted">Plataforma</small>
    @endunless
</div>
