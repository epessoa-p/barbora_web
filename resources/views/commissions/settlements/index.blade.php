@extends('layouts.app')

@section('title', 'Liquidaciones - Barbora')

@section('page')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h4 fw-bold mb-1"><i class="bi bi-receipt"></i> Liquidaciones</h1>
        <p class="text-muted mb-0">Comprobantes de lo pagado a cada barbero.</p>
    </div>
    <a href="{{ route('commissions.index') }}" class="btn btn-primary">
        <i class="bi bi-cash-stack"></i> Comisiones
    </a>
</div>

<form method="GET" class="row g-2 mb-3">
    <div class="col-md-4">
        <select name="personal" class="form-select">
            <option value="">Todos los barberos</option>
            @foreach($staff as $person)
                <option value="{{ $person->id }}" {{ (string) request('personal') === (string) $person->id ? 'selected' : '' }}>
                    {{ $person->full_name }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3 d-flex gap-2">
        <button class="btn btn-outline-secondary"><i class="bi bi-funnel"></i> Filtrar</button>
        @if(request('personal'))
            <a href="{{ route('commissions.settlements.index') }}" class="btn btn-light border">Limpiar</a>
        @endif
    </div>
</form>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Nº</th>
                        <th>Barbero</th>
                        <th>Periodo</th>
                        <th class="text-center">Líneas</th>
                        <th class="text-end">Comisiones</th>
                        <th class="text-end">Propinas</th>
                        <th class="text-end">Total</th>
                        <th class="text-end pe-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($settlements as $settlement)
                        <tr>
                            <td class="ps-3"><code>{{ $settlement->number }}</code></td>
                            <td class="fw-semibold">{{ $settlement->personal?->full_name }}</td>
                            <td class="text-nowrap">{{ $settlement->periodLabel() }}</td>
                            <td class="text-center">{{ $settlement->entries_count }}</td>
                            <td class="text-end">{{ \App\Support\Money::format($settlement->commissions_total, $tenantCompany) }}</td>
                            <td class="text-end text-muted">{{ \App\Support\Money::format($settlement->tips_total, $tenantCompany) }}</td>
                            <td class="text-end fw-semibold">{{ \App\Support\Money::format($settlement->total, $tenantCompany) }}</td>
                            <td class="text-end pe-3">
                                <a href="{{ route('commissions.settlements.show', $settlement) }}" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">Todavía no hay liquidaciones.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="d-flex justify-content-center mt-3">
    {{ $settlements->links() }}
</div>
@endsection
