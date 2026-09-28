@extends('layouts.app')

@section('title', 'Turno de caja - Barbora')

@section('page')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h4 fw-bold mb-1">
            {{ $session->caja?->name }}
            @if($session->isOpen())
                <span class="badge bg-warning align-middle">Abierta</span>
            @else
                <span class="badge bg-{{ $session->differenceColor() }} align-middle">{{ $session->differenceLabel() }}</span>
            @endif
        </h1>
        <p class="text-muted mb-0">
            {{ $session->opened_at->translatedFormat('l d \d\e F \d\e Y, H:i') }}
            @if($session->closed_at) — {{ $session->closed_at->format('H:i') }} @endif
            · {{ $session->durationLabel() }}
        </p>
    </div>
    <div class="d-flex gap-2">
        @if($session->isOpen())
            <a href="{{ route('cash.current', ['caja' => $session->caja_id]) }}" class="btn btn-primary">
                <i class="bi bi-cash-coin"></i> Operar turno
            </a>
        @endif
        <a href="{{ route('cash.sessions.index') }}" class="btn btn-outline-secondary">Volver</a>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        @include('cash.partials.movements-table', ['session' => $session, 'movements' => $session->movements])
    </div>

    <div class="col-lg-5">
        {{-- Cierre Z: el resumen que se imprime o se archiva al acabar el turno --}}
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-3">Resumen del turno</h6>

                <div class="d-flex justify-content-between py-1 border-bottom">
                    <span class="text-muted">Fondo inicial</span>
                    <span>{{ \App\Support\Money::format($session->opening_amount, $tenantCompany) }}</span>
                </div>
                <div class="d-flex justify-content-between py-1 border-bottom">
                    <span class="text-muted">Ingresos</span>
                    <span class="text-success">{{ \App\Support\Money::format($totals['income'], $tenantCompany) }}</span>
                </div>
                <div class="d-flex justify-content-between py-1 border-bottom">
                    <span class="text-muted">Gastos</span>
                    <span class="text-danger">{{ \App\Support\Money::format($totals['expense'], $tenantCompany) }}</span>
                </div>
                <div class="d-flex justify-content-between py-2 fw-bold border-bottom">
                    <span>Resultado del turno</span>
                    <span>{{ \App\Support\Money::format($totals['net'], $tenantCompany) }}</span>
                </div>

                <h6 class="fw-bold mt-4 mb-2">Arqueo de efectivo</h6>
                <div class="d-flex justify-content-between py-1 border-bottom">
                    <span class="text-muted">Esperado en el cajón</span>
                    <span>{{ \App\Support\Money::format($session->isOpen() ? $totals['expected_cash'] : $session->expected_amount, $tenantCompany) }}</span>
                </div>
                @unless($session->isOpen())
                    <div class="d-flex justify-content-between py-1 border-bottom">
                        <span class="text-muted">Contado al cerrar</span>
                        <span>{{ \App\Support\Money::format($session->closing_amount, $tenantCompany) }}</span>
                    </div>
                    <div class="d-flex justify-content-between py-2 fw-bold">
                        <span>Diferencia</span>
                        <span class="text-{{ $session->differenceColor() }}">
                            {{ \App\Support\Money::format($session->difference, $tenantCompany) }}
                        </span>
                    </div>
                @endunless
            </div>
        </div>

        <div class="card border-0 shadow-sm mt-4">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-3">Por método de pago</h6>
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th class="ps-0">Método</th>
                            <th class="text-end">Ingresos</th>
                            <th class="text-end pe-0">Gastos</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($breakdown as $method => $row)
                            @continue($row['count'] === 0)
                            <tr>
                                <td class="ps-0">
                                    {{ $methodNames[$method] ?? \Illuminate\Support\Str::headline($method) }}
                                    @if(in_array($method, $cashMethods, true))
                                        <i class="bi bi-cash text-muted" title="Cuenta en el arqueo: está en el cajón"></i>
                                    @endif
                                </td>
                                <td class="text-end text-success">{{ \App\Support\Money::format($row['income'], $tenantCompany) }}</td>
                                <td class="text-end pe-0 text-danger">{{ \App\Support\Money::format($row['expense'], $tenantCompany) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card border-0 shadow-sm mt-4">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-3">Responsables</h6>
                <p class="mb-1"><strong>Abrió:</strong> {{ $session->openedBy?->name ?? '—' }}</p>
                <p class="mb-1"><strong>Cerró:</strong> {{ $session->closedBy?->name ?? '—' }}</p>
                @if($session->opening_notes)
                    <p class="mb-1"><strong>Notas de apertura:</strong> {{ $session->opening_notes }}</p>
                @endif
                @if($session->closing_notes)
                    <p class="mb-0"><strong>Notas de cierre:</strong> {{ $session->closing_notes }}</p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
