@extends('layouts.app')

@section('title', 'Caja actual - Barbora')

@section('page')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h4 fw-bold mb-1"><i class="bi bi-cash-coin"></i> Caja actual</h1>
        <p class="text-muted mb-0">
            @if($session)
                Turno abierto {{ $session->opened_at->translatedFormat('d/m \a \l\a\s H:i') }}
                · lleva {{ $session->durationLabel() }}
            @else
                Ningún turno abierto.
            @endif
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('cash.sessions.index') }}" class="btn btn-light border">
            <i class="bi bi-clock-history"></i> Turnos
        </a>
        <a href="{{ route('cash.movements') }}" class="btn btn-light border">
            <i class="bi bi-list-ul"></i> Movimientos
        </a>
    </div>
</div>

@if($cajas->count() > 1)
    <form method="GET" class="mb-3" style="max-width: 20rem;">
        <select name="caja" class="form-select" onchange="this.form.submit()">
            @foreach($cajas as $option)
                <option value="{{ $option->id }}" {{ $caja?->id === $option->id ? 'selected' : '' }}>
                    {{ $option->name }}{{ $option->isOpen() ? ' · abierta' : '' }}
                </option>
            @endforeach
        </select>
    </form>
@endif

@if(! $caja)
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5">
            <i class="bi bi-safe text-muted" style="font-size: 2.5rem;"></i>
            <h6 class="fw-semibold mt-3">Todavía no hay ninguna caja</h6>
            <p class="text-muted mb-3">Crea al menos una caja registradora para poder abrir turnos.</p>
            <a href="{{ route('cajas.create') }}" class="btn btn-primary">Crear caja</a>
        </div>
    </div>

@elseif(! $session)
    {{-- Sin turno abierto: el formulario de apertura --}}
    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-1"><i class="bi bi-unlock"></i> Abrir turno en «{{ $caja->name }}»</h6>
                    <p class="text-muted small mb-3">
                        El fondo es el efectivo con el que arranca el cajón. Al cerrar se comparará
                        con lo que cuentes.
                    </p>

                    <form action="{{ route('cash.open') }}" method="POST">
                        @csrf
                        <input type="hidden" name="caja_id" value="{{ $caja->id }}">

                        <div class="mb-3">
                            <label for="opening_amount" class="form-label">Fondo inicial</label>
                            <div class="input-group">
                                <span class="input-group-text">{{ \App\Support\Money::symbol($tenantCompany) }}</span>
                                <input type="number" step="0.01" min="0" id="opening_amount" name="opening_amount"
                                       class="form-control @error('opening_amount') is-invalid @enderror"
                                       value="{{ old('opening_amount', number_format((float) $caja->balance, 2, '.', '')) }}" required>
                                @error('opening_amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <small class="text-muted">Se propone el saldo con el que quedó la caja.</small>
                        </div>

                        <div class="mb-3">
                            <label for="opening_notes" class="form-label">Notas</label>
                            <textarea id="opening_notes" name="opening_notes" rows="2" class="form-control">{{ old('opening_notes') }}</textarea>
                        </div>

                        <button class="btn btn-primary w-100"><i class="bi bi-unlock"></i> Abrir turno</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3">Últimos turnos de esta caja</h6>
                    @forelse($caja->sessions()->with('closedBy')->limit(5)->get() as $past)
                        <a href="{{ route('cash.sessions.show', $past) }}"
                           class="d-flex justify-content-between align-items-center py-2 border-bottom text-decoration-none">
                            <span>
                                <span class="fw-semibold d-block">{{ $past->opened_at->translatedFormat('d/m/Y') }}</span>
                                <small class="text-muted">{{ $past->durationLabel() }} · {{ $past->closedBy?->name ?? '—' }}</small>
                            </span>
                            @if($past->isOpen())
                                <span class="badge bg-warning">Abierta</span>
                            @else
                                <span class="badge bg-{{ $past->differenceColor() }}">{{ $past->differenceLabel() }}</span>
                            @endif
                        </a>
                    @empty
                        <p class="text-muted mb-0">Esta caja todavía no ha tenido turnos.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

@else
    {{-- Turno abierto --}}
    <div class="row g-3 mb-4">
        @foreach([
            ['Fondo inicial', $session->opening_amount, 'bi-wallet2', '#6b7280'],
            ['Ingresos', $totals['income'], 'bi-arrow-down-circle', '#16a34a'],
            ['Gastos', $totals['expense'], 'bi-arrow-up-circle', '#dc2626'],
            ['Efectivo esperado', $totals['expected_cash'], 'bi-cash-stack', '#2563eb'],
        ] as [$label, $value, $icon, $color])
            <div class="col-xl-3 col-md-6">
                <div class="kpi-card">
                    <div class="kpi-body">
                        <div>
                            <div class="kpi-value">{{ \App\Support\Money::format($value, $tenantCompany) }}</div>
                            <div class="kpi-label">{{ $label }}</div>
                        </div>
                        <div class="kpi-icon" style="background: {{ $color }};"><i class="bi {{ $icon }}"></i></div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            @include('cash.partials.movements-table', ['session' => $session, 'movements' => $session->movements])
        </div>

        <div class="col-lg-5">
            @include('cash.partials.movement-form', ['session' => $session])
            @include('cash.partials.close-form', ['session' => $session, 'totals' => $totals])
        </div>
    </div>
@endif
@endsection
