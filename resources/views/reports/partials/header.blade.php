{{--
    Encabezado común: título, periodo, y los botones de exportar e imprimir.
    $export es la clave del reporte en la ruta (ventas, servicios, …) o null.
--}}
<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h4 fw-bold mb-1"><i class="bi {{ $icon ?? 'bi-bar-chart' }}"></i> {{ $title }}</h1>
        <p class="text-muted mb-0">
            {{ $period->label() }}
            @if($period->branchId)
                · {{ $branches->firstWhere('id', $period->branchId)?->name }}
            @endif
            @isset($subtitle)
                <span class="d-block small">{{ $subtitle }}</span>
            @endisset
        </p>
    </div>

    <div class="d-flex gap-2 d-print-none">
        @isset($export)
            @if(auth()->user()->hasPermissionInCompany('reports.export', $tenantCompany))
                <a href="{{ route('reports.export', array_merge(['report' => $export], $period->queryParams())) }}"
                   class="btn btn-outline-success btn-sm">
                    <i class="bi bi-file-earmark-spreadsheet"></i> Excel
                </a>
            @endif
        @endisset

        <button type="button" onclick="window.print()" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-printer"></i> Imprimir / PDF
        </button>

        <a href="{{ route('reports.index', $period->queryParams()) }}" class="btn btn-light border btn-sm">
            <i class="bi bi-grid"></i> Reportes
        </a>
    </div>
</div>
