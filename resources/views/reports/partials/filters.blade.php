{{--
    Barra de periodo compartida por los seis reportes.
    Conserva el filtro al navegar entre ellos y al exportar.
--}}
@php
    $activePreset = request('preset');
@endphp

<div class="card border-0 shadow-sm mb-4 d-print-none">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label small text-muted mb-1">Desde</label>
                <input type="date" name="from" value="{{ $period->from->toDateString() }}" class="form-control form-control-sm">
            </div>
            <div class="col-auto">
                <label class="form-label small text-muted mb-1">Hasta</label>
                <input type="date" name="to" value="{{ $period->to->toDateString() }}" class="form-control form-control-sm">
            </div>

            @if($branches->count() > 1)
                <div class="col-auto">
                    <label class="form-label small text-muted mb-1">Sucursal</label>
                    <select name="branch_id" class="form-select form-select-sm">
                        <option value="">Todas</option>
                        @foreach($branches as $branch)
                            <option value="{{ $branch->id }}" {{ $period->branchId === $branch->id ? 'selected' : '' }}>
                                {{ $branch->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div class="col-auto">
                <button class="btn btn-sm btn-primary"><i class="bi bi-funnel"></i> Aplicar</button>
            </div>

            <div class="col-12 col-xl d-flex gap-1 flex-wrap justify-content-xl-end">
                @foreach(\App\Support\ReportPeriod::PRESETS as $key => $label)
                    <a href="{{ request()->url() }}?preset={{ $key }}{{ $period->branchId ? '&branch_id='.$period->branchId : '' }}"
                       class="btn btn-sm {{ $activePreset === $key ? 'btn-secondary' : 'btn-light border' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>
        </form>
    </div>
</div>
