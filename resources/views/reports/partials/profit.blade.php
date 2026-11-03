{{--
    «Ganancia por lo vendido» (ProfitReport): por productos y por servicios,
    por día, por producto y por venta. Ambos / Ventas / Servicios, y si se unen
    o no las ventas rápidas (productos sin ficha: sin costo conocido).
--}}
@php
    $money = fn ($v) => \App\Support\Money::format($v, $tenantCompany);
    $pct = fn ($v) => $v === null ? '—' : number_format($v, 1).'%';
    $pctClass = fn ($v) => $v === null ? 'text-muted' : ($v < 15 ? 'text-warning' : 'text-success');
    $scope = $profit['scope'];
@endphp

<div class="d-flex justify-content-between align-items-center mt-5 mb-3 flex-wrap gap-2">
    <h2 class="h5 fw-bold mb-0"><i class="bi bi-graph-up-arrow"></i> Ganancia por lo vendido</h2>

    <div class="d-flex flex-wrap gap-3 align-items-center d-print-none">
        <div class="btn-group btn-group-sm" role="group">
            @foreach(['ambos' => ['Ambos', 'bi-circle-half'], 'ventas' => ['Ventas', 'bi-cart'], 'servicios' => ['Servicios', 'bi-scissors']] as $value => [$label, $icon])
                <a href="{{ request()->fullUrlWithQuery(['scope' => $value]) }}"
                   class="btn {{ $scope === $value ? 'btn-primary' : 'btn-outline-primary' }}">
                    <i class="bi {{ $icon }}"></i> {{ $label }}
                </a>
            @endforeach
        </div>

        <a href="{{ request()->fullUrlWithQuery(['quick' => $profit['include_quick'] ? 0 : 1]) }}"
           class="text-decoration-none text-reset d-flex align-items-center gap-2" title="Productos vendidos sin registrar en inventario">
            <span class="form-check form-switch mb-0">
                <input class="form-check-input" type="checkbox" role="switch" {{ $profit['include_quick'] ? 'checked' : '' }} tabindex="-1" style="pointer-events: none">
            </span>
            <span class="small">
                Unir ventas rápidas
                <span class="text-muted d-block" style="font-size: .75rem">Productos no cargados: no tienen costo conocido.</span>
            </span>
        </a>
    </div>
</div>

@if($profit['quick']['count'] > 0 && ! $profit['include_quick'])
    <div class="alert alert-light border small py-2">
        <i class="bi bi-lightning-charge"></i>
        Hubo {{ $profit['quick']['count'] }} ventas rápidas por {{ $money($profit['quick']['revenue']) }} que no están sumadas aquí.
    </div>
@endif

<div class="row g-4">
    <div class="col-lg-5">
        {{-- Resumen --}}
        <div class="card border-0 shadow-sm mb-4" style="background: #f0fdf4">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small">Ganancia</div>
                        <div class="display-6 fw-bold text-success">{{ $money($profit['summary']['profit']) }}</div>
                    </div>
                    <span class="badge rounded-pill bg-success-subtle text-success fs-6">Margen {{ $pct($profit['summary']['margin_pct']) }}</span>
                </div>
                <hr>
                <div class="row">
                    <div class="col-6"><div class="text-muted small">Ingresos</div><div class="fw-bold">{{ $money($profit['summary']['revenue']) }}</div></div>
                    <div class="col-6"><div class="text-muted small">Costo</div><div class="fw-bold">{{ $money($profit['summary']['cost']) }}</div></div>
                </div>
            </div>
        </div>

        {{-- Ventas (productos) y servicios --}}
        @foreach([
            ['products', 'Ventas', 'bi-cart', '#0ea5e9', 'ventas', 'Costo de productos y comisiones'],
            ['services', 'Servicios', 'bi-scissors', '#f59e0b', 'servicios', 'Comisiones de los barberos'],
        ] as [$key, $title, $icon, $color, $scopeKey, $costLabel])
            @continue($scope !== 'ambos' && $scope !== $scopeKey)
            @php($block = $profit[$key])
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <h6 class="fw-bold mb-0"><i class="bi {{ $icon }}" style="color: {{ $color }}"></i> {{ $title }}</h6>
                        <small class="text-muted">{{ $block['sales'] }} ventas</small>
                    </div>
                    <div class="d-flex justify-content-between py-1"><span>Ingresos</span><span>{{ $money($block['revenue']) }}</span></div>
                    <div class="d-flex justify-content-between py-1 text-muted border-bottom"><span>{{ $costLabel }}</span><span>− {{ $money($block['cost']) }}</span></div>
                    <div class="d-flex justify-content-between pt-2 fw-bold">
                        <span>Ganancia</span>
                        <span class="text-success">{{ $money($block['profit']) }} <small class="{{ $pctClass($block['margin_pct']) }} fw-normal">({{ $pct($block['margin_pct']) }})</small></span>
                    </div>
                </div>
            </div>
        @endforeach

        {{-- Por día --}}
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <h6 class="fw-bold mb-3"><i class="bi bi-calendar3"></i> Por día</h6>
                @forelse($profit['daily'] as $day)
                    <div class="d-flex justify-content-between align-items-baseline py-1 border-bottom">
                        <span>
                            <strong>{{ \Carbon\Carbon::parse($day['date'])->translatedFormat('D d/m') }}</strong>
                            <small class="text-muted ms-2">Vendido {{ $money($day['revenue']) }}</small>
                        </span>
                        <span class="fw-bold text-success">{{ $money($day['profit']) }}</span>
                    </div>
                @empty
                    <p class="text-muted small mb-0">Sin ventas en el periodo.</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        {{-- Productos: más ganancia / margen bajo --}}
        @if($scope !== 'servicios')
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <h6 class="fw-bold mb-3"><i class="bi bi-trophy text-warning"></i> Productos</h6>
                    <ul class="nav nav-pills nav-fill mb-3 small" role="tablist">
                        <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#prod-top" type="button">Más ganancia</button></li>
                        <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#prod-low" type="button">Margen bajo</button></li>
                    </ul>
                    <div class="tab-content">
                        @foreach(['prod-top' => $profit['top_products'], 'prod-low' => $profit['low_margin_products']] as $tab => $rows)
                            <div class="tab-pane fade {{ $tab === 'prod-top' ? 'show active' : '' }}" id="{{ $tab }}">
                                @forelse($rows as $row)
                                    <div class="d-flex justify-content-between py-2 border-bottom">
                                        <div>
                                            <div class="fw-semibold">
                                                {{ $row['name'] }}
                                                @if($row['quick'])<span class="badge bg-light text-dark border">Rápida</span>@endif
                                            </div>
                                            <small class="text-muted">{{ rtrim(rtrim(number_format($row['quantity'], 2, '.', ''), '0'), '.') }} u. · vendido {{ $money($row['revenue']) }}</small>
                                        </div>
                                        <div class="text-end">
                                            <div class="fw-bold">{{ $money($row['profit']) }}</div>
                                            <small class="{{ $pctClass($row['margin_pct']) }}">{{ $pct($row['margin_pct']) }}</small>
                                        </div>
                                    </div>
                                @empty
                                    <p class="text-muted small mb-0">Sin productos vendidos en el periodo.</p>
                                @endforelse
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        {{-- Cada venta --}}
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <h6 class="fw-bold mb-0"><i class="bi bi-receipt"></i> Ventas</h6>
                    <small class="text-muted">{{ count($profit['sales']) }} registros</small>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <tbody>
                            @forelse($profit['sales'] as $sale)
                                <tr>
                                    <td class="ps-0">
                                        <a href="{{ route('sales.show', $sale['id']) }}" class="fw-semibold"><code>{{ $sale['number'] }}</code></a>
                                        <small class="text-muted d-block">
                                            {{ \Carbon\Carbon::parse($sale['sold_at'])->format('d/m H:i') }} · {{ $sale['client'] ?? 'Sin cliente' }}
                                        </small>
                                        <small class="text-muted">Vendido {{ $money($sale['revenue']) }} · costo {{ $money($sale['cost']) }}</small>
                                    </td>
                                    <td class="text-end pe-0">
                                        <div class="fw-bold text-success">{{ $money($sale['profit']) }}</div>
                                        <small class="{{ $pctClass($sale['margin_pct']) }}">{{ $pct($sale['margin_pct']) }}</small>
                                    </td>
                                </tr>
                            @empty
                                <tr><td class="text-muted small">Sin ventas en el periodo.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
