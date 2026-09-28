@extends('layouts.app')

@section('title', 'Turnos de caja - Barbora')

@section('page')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h4 fw-bold mb-1"><i class="bi bi-clock-history"></i> Turnos de caja</h1>
        <p class="text-muted mb-0">Cada apertura y su arqueo al cerrar.</p>
    </div>
    <a href="{{ route('cash.current') }}" class="btn btn-primary">
        <i class="bi bi-cash-coin"></i> Caja actual
    </a>
</div>

<form method="GET" class="row g-2 mb-3">
    <div class="col-md-4">
        <select name="caja" class="form-select">
            <option value="">Todas las cajas</option>
            @foreach($cajas as $caja)
                <option value="{{ $caja->id }}" {{ (string) request('caja') === (string) $caja->id ? 'selected' : '' }}>
                    {{ $caja->name }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4">
        <select name="status" class="form-select">
            <option value="">Todos los estados</option>
            <option value="abierta" {{ request('status') === 'abierta' ? 'selected' : '' }}>Abiertas</option>
            <option value="cerrada" {{ request('status') === 'cerrada' ? 'selected' : '' }}>Cerradas</option>
        </select>
    </div>
    <div class="col-md-4 d-flex gap-2">
        <button class="btn btn-outline-secondary"><i class="bi bi-funnel"></i> Filtrar</button>
        @if(request('caja') || request('status'))
            <a href="{{ route('cash.sessions.index') }}" class="btn btn-light border">Limpiar</a>
        @endif
    </div>
</form>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Apertura</th>
                        <th>Caja</th>
                        <th>Duración</th>
                        <th class="text-end">Fondo</th>
                        <th class="text-end">Esperado</th>
                        <th class="text-end">Contado</th>
                        <th class="text-center">Arqueo</th>
                        <th class="text-end pe-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($sessions as $session)
                        <tr>
                            <td class="ps-3">
                                <span class="fw-semibold d-block">{{ $session->opened_at->translatedFormat('d/m/Y H:i') }}</span>
                                <small class="text-muted">{{ $session->openedBy?->name ?? '—' }}</small>
                            </td>
                            <td>{{ $session->caja?->name }}</td>
                            <td>{{ $session->durationLabel() }}</td>
                            <td class="text-end">{{ \App\Support\Money::format($session->opening_amount, $tenantCompany) }}</td>
                            <td class="text-end">
                                {{ $session->isOpen() ? '—' : \App\Support\Money::format($session->expected_amount, $tenantCompany) }}
                            </td>
                            <td class="text-end">
                                {{ $session->isOpen() ? '—' : \App\Support\Money::format($session->closing_amount, $tenantCompany) }}
                            </td>
                            <td class="text-center">
                                @if($session->isOpen())
                                    <span class="badge bg-warning">Abierta</span>
                                @else
                                    <span class="badge bg-{{ $session->differenceColor() }}">
                                        {{ $session->differenceLabel() }}
                                        @if(abs((float) $session->difference) >= 0.01)
                                            ({{ \App\Support\Money::format(abs((float) $session->difference), $tenantCompany) }})
                                        @endif
                                    </span>
                                @endif
                            </td>
                            <td class="text-end pe-3">
                                <a href="{{ route('cash.sessions.show', $session) }}" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">Todavía no hay turnos registrados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="d-flex justify-content-center mt-3">
    {{ $sessions->links() }}
</div>
@endsection
