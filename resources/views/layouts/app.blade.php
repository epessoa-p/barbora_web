@extends('layouts.base')

@section('content')
@php
    $authUser = auth()->user();
    // $tenantCompany y $globalMode los comparte el middleware SetTenant.
    $currentCompany = $tenantCompany ?? null;
    $activeCompanies = $authUser->activeCompanies()->get();
    $subscription = $currentCompany?->subscription;
    $isGlobalMode = $globalMode ?? false;

    $can = fn (string $permission) => $authUser->hasPermissionInCompany($permission, $currentCompany);
    $hasModule = fn (string $module) => $authUser->is_super_admin || (bool) $currentCompany?->planAllows($module);

    // Quién es el usuario dentro de la empresa activa: su cargo y su nombre de
    // personal. Si todavía no tiene ficha de personal, se cae al rol y al
    // nombre de la cuenta.
    $personal = $currentCompany ? $authUser->personal : null;
    $identityTitle = $personal?->cargo?->name ?? $currentCompany?->getRoleForUser($authUser)?->name;
    $identityName = $personal?->full_name ?? $authUser->name;

    // Un único menú para la barra lateral y el offcanvas: antes estaban
    // duplicados y ya habían divergido.
    // El color identifica cada sección: se mantiene en el punto y en el borde
    // aunque esté plegada, y marca el recuadro que la delimita al abrirla.
    $navSections = collect([
        [
            'key' => 'principal',
            'label' => 'Principal',
            'color' => '#2563eb',
            'items' => [
                ['show' => true, 'route' => 'dashboard', 'pattern' => 'dashboard', 'icon' => 'bi-house', 'label' => 'Dashboard'],
            ],
        ],
        [
            'key' => 'plataforma',
            'label' => 'Plataforma',
            'color' => '#7c3aed',
            'items' => [
                ['show' => $authUser->is_super_admin, 'route' => 'companies.index', 'pattern' => 'companies.*', 'icon' => 'bi-building', 'label' => 'Empresas'],
                ['show' => $authUser->is_super_admin, 'route' => 'plans.index', 'pattern' => 'plans.*', 'icon' => 'bi-box-seam', 'label' => 'Planes'],
                ['show' => $authUser->is_super_admin, 'route' => 'roles.index', 'pattern' => 'roles.*', 'icon' => 'bi-shield-lock', 'label' => 'Roles'],
                ['show' => $can('users.view'), 'route' => 'users.index', 'pattern' => 'users.*', 'icon' => 'bi-person-gear', 'label' => 'Usuarios'],
            ],
        ],
        [
            'key' => 'agenda',
            'label' => 'Agenda',
            'color' => '#16a34a',
            'items' => [
                ['show' => $can('appointments.view') && $hasModule('agenda'), 'route' => 'appointments.calendar', 'pattern' => 'appointments.calendar', 'icon' => 'bi-calendar3', 'label' => 'Calendario'],
                ['show' => $can('appointments.view') && $hasModule('agenda'), 'route' => 'appointments.index', 'pattern' => 'appointments.index', 'icon' => 'bi-calendar-check', 'label' => 'Citas del día'],
                ['show' => $can('appointments.create') && $hasModule('agenda'), 'route' => 'appointments.create', 'pattern' => 'appointments.create', 'icon' => 'bi-calendar-plus', 'label' => 'Nueva cita'],
                ['show' => $can('agenda_blocks.view') && $hasModule('agenda'), 'route' => 'agenda-blocks.index', 'pattern' => 'agenda-blocks.*', 'icon' => 'bi-slash-circle', 'label' => 'Bloqueos'],
                ['show' => $can('appointments.view') && $hasModule('agenda') && $hasModule('reservas_online'), 'route' => 'reminders.index', 'pattern' => 'reminders.*', 'icon' => 'bi-bell', 'label' => 'Recordatorios'],
            ],
        ],
        [
            'key' => 'catalogo',
            'label' => 'Catálogo',
            'color' => '#0d9488',
            'items' => [
                ['show' => $can('services.view') && $hasModule('agenda'), 'route' => 'services.index', 'pattern' => 'services.*', 'icon' => 'bi-scissors', 'label' => 'Servicios'],
                ['show' => $can('service_categories.view') && $hasModule('agenda'), 'route' => 'service-categories.index', 'pattern' => 'service-categories.*', 'icon' => 'bi-tags', 'label' => 'Categorías'],
            ],
        ],
        [
            'key' => 'clientes',
            'label' => 'Clientes',
            'color' => '#db2777',
            'items' => [
                ['show' => $can('clients.view') && $hasModule('clientes'), 'route' => 'clients.index', 'pattern' => 'clients.*', 'icon' => 'bi-people', 'label' => 'Clientes'],
            ],
        ],
        [
            'key' => 'ventas',
            'label' => 'Ventas',
            'color' => '#0ea5e9',
            'items' => [
                ['show' => $can('sales.create') && $hasModule('pos'), 'route' => 'sales.create', 'pattern' => 'sales.create', 'icon' => 'bi-cart-plus', 'label' => 'Nueva venta'],
                ['show' => $can('sales.view') && $hasModule('pos'), 'route' => 'sales.index', 'pattern' => 'sales.index', 'icon' => 'bi-receipt', 'label' => 'Historial'],
            ],
        ],
        [
            'key' => 'comisiones',
            'label' => 'Comisiones',
            'color' => '#e11d48',
            'items' => [
                ['show' => $can('commissions.view') && $hasModule('comisiones'), 'route' => 'commissions.index', 'pattern' => 'commissions.index', 'icon' => 'bi-cash-stack', 'label' => 'Resumen'],
                ['show' => $can('commissions.view') && $hasModule('comisiones'), 'route' => 'commissions.settlements.index', 'pattern' => 'commissions.settlements.*', 'icon' => 'bi-receipt', 'label' => 'Liquidaciones'],
                ['show' => $can('commission_rules.view') && $hasModule('comisiones'), 'route' => 'commission-rules.index', 'pattern' => 'commission-rules.*', 'icon' => 'bi-percent', 'label' => 'Reglas'],
            ],
        ],
        [
            'key' => 'reportes',
            'label' => 'Reportes',
            'color' => '#0f766e',
            'items' => [
                ['show' => $can('reports.view') && $hasModule('estadisticas'), 'route' => 'reports.index', 'pattern' => 'reports.index', 'icon' => 'bi-grid', 'label' => 'Resumen'],
                ['show' => $can('reports.view') && $hasModule('estadisticas'), 'route' => 'reports.sales', 'pattern' => 'reports.sales', 'icon' => 'bi-graph-up-arrow', 'label' => 'Ventas'],
                ['show' => $can('reports.view') && $hasModule('estadisticas'), 'route' => 'reports.services', 'pattern' => 'reports.services', 'icon' => 'bi-scissors', 'label' => 'Servicios'],
                ['show' => $can('reports.view') && $hasModule('estadisticas'), 'route' => 'reports.staff', 'pattern' => 'reports.staff', 'icon' => 'bi-person-badge', 'label' => 'Barberos'],
                ['show' => $can('reports.view') && $hasModule('estadisticas'), 'route' => 'reports.products', 'pattern' => 'reports.products', 'icon' => 'bi-box-seam', 'label' => 'Productos'],
                ['show' => $can('reports.view') && $hasModule('estadisticas'), 'route' => 'reports.clients', 'pattern' => 'reports.clients', 'icon' => 'bi-people', 'label' => 'Clientes'],
                ['show' => $can('reports.view') && $hasModule('estadisticas'), 'route' => 'reports.earnings', 'pattern' => 'reports.earnings', 'icon' => 'bi-cash-coin', 'label' => 'Ganancias'],
            ],
        ],
        [
            'key' => 'inventario',
            'label' => 'Inventario',
            'color' => '#7c3aed',
            'items' => [
                ['show' => $can('stock.view') && $hasModule('inventario'), 'route' => 'stock.index', 'pattern' => 'stock.index', 'icon' => 'bi-boxes', 'label' => 'Existencias'],
                ['show' => $can('products.view') && $hasModule('inventario'), 'route' => 'products.index', 'pattern' => 'products.*', 'icon' => 'bi-box', 'label' => 'Productos'],
                ['show' => $can('product_categories.view') && $hasModule('inventario'), 'route' => 'product-categories.index', 'pattern' => 'product-categories.*', 'icon' => 'bi-tags', 'label' => 'Categorías'],
                ['show' => $can('stock.view') && $hasModule('inventario'), 'route' => 'stock.movements', 'pattern' => 'stock.movements', 'icon' => 'bi-clock-history', 'label' => 'Movimientos'],
                ['show' => $can('warehouses.view') && $hasModule('inventario'), 'route' => 'warehouses.index', 'pattern' => 'warehouses.*', 'icon' => 'bi-building', 'label' => 'Almacenes'],
            ],
        ],
        [
            'key' => 'caja',
            'label' => 'Caja',
            'color' => '#ca8a04',
            'items' => [
                ['show' => $can('cash.view') && $hasModule('caja'), 'route' => 'cash.current', 'pattern' => 'cash.current', 'icon' => 'bi-cash-coin', 'label' => 'Caja actual'],
                ['show' => $can('cash.view') && $hasModule('caja'), 'route' => 'cash.movements', 'pattern' => 'cash.movements', 'icon' => 'bi-list-ul', 'label' => 'Movimientos'],
                ['show' => $can('cash.view') && $hasModule('caja'), 'route' => 'cash.sessions.index', 'pattern' => 'cash.sessions.*', 'icon' => 'bi-clock-history', 'label' => 'Turnos'],
            ],
        ],
        [
            'key' => 'administracion',
            'label' => 'Administración',
            'color' => '#f59e0b',
            'items' => [
                ['show' => $can('cargos.view'), 'route' => 'cargos.index', 'pattern' => 'cargos.*', 'icon' => 'bi-briefcase', 'label' => 'Cargos'],
                ['show' => $can('personal.view'), 'route' => 'personal.index', 'pattern' => 'personal.*', 'icon' => 'bi-person-vcard', 'label' => 'Personal'],
                ['show' => $can('schedules.view'), 'route' => 'schedules.index', 'pattern' => 'schedules.*', 'icon' => 'bi-calendar-week', 'label' => 'Horarios'],
                ['show' => $can('branches.view'), 'route' => 'branches.index', 'pattern' => 'branches.*', 'icon' => 'bi-diagram-2', 'label' => 'Sucursales'],
                // Las cajas registradoras son dato maestro, pero el enlace se
                // oculta si el plan no trae el módulo; la ruta lo exige igual.
                ['show' => $can('cajas.view') && $hasModule('caja'), 'route' => 'cajas.index', 'pattern' => 'cajas.*', 'icon' => 'bi-safe', 'label' => 'Cajas'],
                // Administrativo: una barbería configura cómo cobra aunque solo
                // tenga contratada la agenda.
                ['show' => $can('settings.view') && $currentCompany, 'route' => 'company-profile.edit', 'pattern' => 'company-profile.*', 'icon' => 'bi-shop', 'label' => 'Datos de la barbería'],
                ['show' => $can('settings.view'), 'route' => 'payment-methods.index', 'pattern' => 'payment-methods.*', 'icon' => 'bi-credit-card', 'label' => 'Métodos de pago'],
            ],
        ],
    ])
    ->map(fn ($section) => [...$section, 'items' => array_values(array_filter($section['items'], fn ($i) => $i['show']))])
    ->filter(fn ($section) => count($section['items']) > 0)
    ->values()
    ->all();
@endphp

<div class="app-shell d-flex">
    <aside class="app-sidebar" id="appSidebar">
        <div class="sidebar-brand">
            <img src="{{ asset('img/logo.svg') }}" alt="Barbora" class="brand-icon">
            <div>
                <div class="brand-title">BARBORA</div>
                <small class="text-muted">{{ $currentCompany?->name ?? 'Plataforma' }}</small>
            </div>
        </div>

        @include('layouts.partials.nav', ['navSections' => $navSections, 'idPrefix' => 'side'])
    </aside>

    <main class="app-main">
        <nav class="navbar navbar-expand-lg app-topbar mb-4">
            <div class="container-fluid px-0">
                <div class="d-flex align-items-center gap-3">
                    <button class="btn btn-icon" type="button" id="sidebarToggle"
                            aria-label="Mostrar u ocultar el menú">
                        <i class="bi bi-list"></i>
                    </button>

                    <span class="topbar-label">Overview</span>
                    <span class="topbar-separator">|</span>

                    @if($isGlobalMode)
                        <span class="topbar-context">Modo Global</span>
                    @elseif($currentCompany)
                        <span class="topbar-context">
                            @if($identityTitle)
                                <span class="topbar-role">{{ $identityTitle }}</span>
                                <span class="topbar-separator mx-1">·</span>
                            @endif
                            {{ $identityName }}
                        </span>
                    @else
                        <span class="topbar-context">Sin empresa activa</span>
                    @endif

                    @if($subscription)
                        @php
                            $badge = match (true) {
                                $subscription->isBlocked() => 'danger',
                                $subscription->inGrace() => 'warning',
                                $subscription->onTrial() => 'info',
                                $subscription->isCurrent() => 'success',
                                default => 'danger',
                            };
                        @endphp
                        <span class="badge bg-{{ $badge }}" title="Plan {{ $subscription->plan?->name }}">
                            {{ $subscription->statusLabel() }}
                        </span>
                    @endif

                    @if(! $authUser->is_super_admin && $activeCompanies->count() > 1)
                        <div class="dropdown">
                            <button class="btn btn-outline-light btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-buildings"></i> Empresa
                            </button>
                            <ul class="dropdown-menu shadow border-0">
                                @foreach($activeCompanies as $company)
                                    <li>
                                        <form action="{{ route('set-company', $company->id) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="dropdown-item d-flex justify-content-between align-items-center">
                                                <span>{{ $company->name }}</span>
                                                @if($currentCompany && $currentCompany->id === $company->id)
                                                    <i class="bi bi-check-lg text-success"></i>
                                                @endif
                                            </button>
                                        </form>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>

                <div class="d-flex align-items-center gap-2">
                    <div class="dropdown">
                        <button class="btn btn-icon" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                            <li><span class="dropdown-item-text fw-semibold">{{ $identityName }}</span></li>
                            @if($identityTitle)
                                <li><span class="dropdown-item-text text-muted small">{{ $identityTitle }}</span></li>
                            @endif
                            <li><span class="dropdown-item-text text-muted small">{{ $authUser->email }}</span></li>
                        </ul>
                    </div>
                    <form action="{{ route('logout') }}" method="POST" class="m-0">
                        @csrf
                        <button class="btn btn-logout" type="submit">
                            <i class="bi bi-box-arrow-right"></i> Cerrar sesion
                        </button>
                    </form>
                </div>
            </div>
        </nav>

        @if(isset($breadcrumbs))
            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb">
                    @foreach($breadcrumbs as $label => $url)
                        @if($loop->last)
                            <li class="breadcrumb-item active">{{ $label }}</li>
                        @else
                            <li class="breadcrumb-item"><a href="{{ $url }}" class="text-decoration-none">{{ $label }}</a></li>
                        @endif
                    @endforeach
                </ol>
            </nav>
        @endif

        @if($isGlobalMode)
            <div class="alert alert-warning d-flex justify-content-between align-items-center" role="alert">
                <div>
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <strong>Modo Global</strong> — estás viendo datos de <strong>todas</strong> las empresas.
                </div>
                <a href="{{ route('companies.index') }}" class="btn btn-sm btn-outline-dark">
                    Entrar como empresa…
                </a>
            </div>
        @elseif($authUser->is_super_admin && $currentCompany)
            <div class="alert alert-info d-flex justify-content-between align-items-center" role="alert">
                <div>
                    <i class="bi bi-eye-fill"></i>
                    Estás viendo la plataforma como <strong>{{ $currentCompany->name }}</strong>.
                </div>
                <form action="{{ route('exit-company') }}" method="POST" class="m-0">
                    @csrf
                    <button class="btn btn-sm btn-outline-dark" type="submit">Salir del modo empresa</button>
                </form>
            </div>
        @endif

        @if($message = session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle"></i> {{ $message }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if($message = session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-circle"></i> {{ $message }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-circle"></i>
                @if($errors->count() === 1)
                    {{ $errors->first() }}
                @else
                    <ul class="mb-0 mt-2">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                @endif
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @yield('page')
    </main>
</div>

<div class="offcanvas offcanvas-start app-offcanvas" tabindex="-1" id="appSidebarMobile" aria-labelledby="appSidebarMobileLabel">
    <div class="offcanvas-header">
        <div class="sidebar-brand m-0 p-0 border-0">
            <img src="{{ asset('img/logo.svg') }}" alt="Barbora" class="brand-icon">
            <div>
                <div class="brand-title" id="appSidebarMobileLabel">BARBORA</div>
                <small class="text-muted">{{ $currentCompany?->name ?? 'Plataforma' }}</small>
            </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
    </div>
    <div class="offcanvas-body">
        @include('layouts.partials.nav', ['navSections' => $navSections, 'idPrefix' => 'mob'])
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const BREAKPOINT = 992;
    const COLLAPSE_KEY = 'barbora.sidebar.collapsed';
    const SECTIONS_KEY = 'barbora.nav.closed';

    const shell = document.querySelector('.app-shell');
    const toggle = document.getElementById('sidebarToggle');

    // ── Botón hamburguesa ───────────────────────────────────────────────────
    // En móvil abre el panel lateral; en escritorio pliega la barra fija.
    if (shell && localStorage.getItem(COLLAPSE_KEY) === '1') {
        shell.classList.add('sidebar-collapsed');
    }

    toggle?.addEventListener('click', function () {
        if (window.innerWidth < BREAKPOINT) {
            const el = document.getElementById('appSidebarMobile');
            if (el && window.bootstrap) {
                window.bootstrap.Offcanvas.getOrCreateInstance(el).toggle();
            }
            return;
        }

        const collapsed = shell.classList.toggle('sidebar-collapsed');
        localStorage.setItem(COLLAPSE_KEY, collapsed ? '1' : '0');
    });

    // ── Secciones desplegables ──────────────────────────────────────────────
    // Se recuerda qué secciones cerró el usuario. La que contiene la página
    // actual se abre siempre, por encima de lo guardado.
    const closed = new Set(JSON.parse(localStorage.getItem(SECTIONS_KEY) || '[]'));

    document.querySelectorAll('.nav-section').forEach(function (section) {
        const key = section.dataset.section;
        const panel = section.querySelector('.collapse');
        const button = section.querySelector('.nav-section-toggle');
        if (!panel || !button) return;

        const hasActive = panel.querySelector('.app-link.active') !== null;

        if (!hasActive && closed.has(key)) {
            panel.classList.remove('show');
            button.classList.add('collapsed');
            button.setAttribute('aria-expanded', 'false');
        }

        panel.addEventListener('shown.bs.collapse', function () {
            closed.delete(key);
            localStorage.setItem(SECTIONS_KEY, JSON.stringify([...closed]));
        });

        panel.addEventListener('hidden.bs.collapse', function () {
            closed.add(key);
            localStorage.setItem(SECTIONS_KEY, JSON.stringify([...closed]));
        });
    });
})();
</script>
@endpush
