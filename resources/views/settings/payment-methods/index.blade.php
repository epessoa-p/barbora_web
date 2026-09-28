@extends('layouts.app')

@section('title', 'Métodos de pago - Barbora')

@section('page')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h4 fw-bold mb-1"><i class="bi bi-credit-card"></i> Métodos de pago</h1>
        <p class="text-muted mb-0">Cómo cobra tu barbería.</p>
    </div>
    @if(auth()->user()->hasPermissionInCompany('settings.edit', $tenantCompany))
        <a href="{{ route('payment-methods.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Nuevo método
        </a>
    @endif
</div>

<div class="alert alert-info py-2 small d-flex gap-2 align-items-start">
    <i class="bi bi-info-circle mt-1"></i>
    <span>
        Solo los métodos marcados como <strong>efectivo</strong> entran en el arqueo de caja,
        porque son los únicos que de verdad están en el cajón. Lo cobrado por tarjeta o por
        billetera está en el banco: si contara, el turno cuadraría mal todos los días.
    </span>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Método</th>
                        <th class="text-center">Entra al cajón</th>
                        <th class="text-center">Pide referencia</th>
                        <th class="text-center">Estado</th>
                        <th class="text-end pe-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($methods as $method)
                        <tr class="{{ $method->active ? '' : 'opacity-50' }}">
                            <td class="ps-3">
                                <span class="fw-semibold">{{ $method->name }}</span>
                                <small class="text-muted d-block"><code>{{ $method->slug }}</code></small>
                            </td>
                            <td class="text-center">
                                @if($method->counts_as_cash)
                                    <span class="badge bg-success-subtle text-success">
                                        <i class="bi bi-cash"></i> Sí
                                    </span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-center">
                                {!! $method->requires_reference
                                    ? '<i class="bi bi-check-lg text-success"></i>'
                                    : '<span class="text-muted">—</span>' !!}
                            </td>
                            <td class="text-center">
                                <span class="badge bg-{{ $method->active ? 'primary' : 'secondary' }}">
                                    {{ $method->active ? 'Activo' : 'De baja' }}
                                </span>
                            </td>
                            <td class="text-end pe-3">
                                @if(auth()->user()->hasPermissionInCompany('settings.edit', $tenantCompany))
                                    <div class="d-flex gap-1 justify-content-end">
                                        <a href="{{ route('payment-methods.edit', $method) }}"
                                           class="btn btn-sm btn-outline-secondary">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <form action="{{ route('payment-methods.destroy', $method) }}" method="POST"
                                              onsubmit="return confirm('¿Dar de baja «{{ $method->name }}»?')">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">
                                No hay métodos de pago. Crea al menos uno para poder cobrar.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<p class="text-muted small mt-3">
    <i class="bi bi-shield-check"></i>
    Un método que ya se usó en alguna venta no se elimina: se da de baja, para que
    el historial siga diciendo con qué se cobró.
</p>
@endsection
