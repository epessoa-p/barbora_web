@extends('layouts.app')

@section('title', 'Reglas de comisión - Barbora')

@section('page')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h4 fw-bold mb-1"><i class="bi bi-percent"></i> Reglas de comisión</h1>
        <p class="text-muted mb-0">Cuánto se lleva cada barbero por lo que hace.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('commissions.index') }}" class="btn btn-light border">
            <i class="bi bi-cash-stack"></i> Comisiones
        </a>
        <a href="{{ route('commission-rules.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Nueva regla
        </a>
    </div>
</div>

@unless($hasRules)
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <strong>Sin reglas activas no se devenga ninguna comisión.</strong>
        Las ventas que se cobren mientras tanto no generarán nada, y eso no se
        puede recalcular después.
    </div>
@endunless

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Prioridad</th>
                        <th>Se aplica a</th>
                        <th>Sobre</th>
                        <th class="text-end">Comisión</th>
                        <th class="text-center">Estado</th>
                        <th class="text-end pe-3">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rules as $rule)
                        <tr class="{{ $rule->active ? '' : 'opacity-50' }}">
                            <td class="ps-3">
                                <span class="badge bg-light text-dark border">{{ $rule->specificity() }}</span>
                            </td>
                            <td class="fw-semibold">{{ $rule->targetLabel() }}</td>
                            <td>{{ $rule->scopeLabel() }}</td>
                            <td class="text-end fw-semibold">
                                @if($rule->isPercentage())
                                    {{ $rule->percentLabel() }}
                                @else
                                    {{ \App\Support\Money::format($rule->value, $tenantCompany) }}
                                    <small class="text-muted d-block">por línea</small>
                                @endif
                            </td>
                            <td class="text-center">
                                <span class="badge {{ $rule->active ? 'bg-success' : 'bg-secondary' }}">
                                    {{ $rule->active ? 'Activa' : 'Inactiva' }}
                                </span>
                            </td>
                            <td class="text-end pe-3">
                                <a href="{{ route('commission-rules.edit', $rule) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form action="{{ route('commission-rules.destroy', $rule) }}" method="POST" class="d-inline"
                                      onsubmit="return confirm('¿Eliminar esta regla? Lo ya devengado no cambia.')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                Todavía no hay reglas. Crea la primera para empezar a devengar comisiones.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mt-4">
    <div class="card-body">
        <h6 class="fw-bold mb-2">Cómo se elige la regla</h6>
        <p class="text-muted small mb-2">
            Para cada línea de una venta se busca la regla más específica que encaje.
            Gana la de prioridad más baja:
        </p>
        <ol class="text-muted small mb-2">
            <li><strong>1</strong> — ese barbero, ese servicio concreto</li>
            <li><strong>2</strong> — ese barbero, todos los servicios o todos los productos</li>
            <li><strong>3</strong> — ese servicio concreto, cualquier barbero</li>
            <li><strong>4</strong> — todos los servicios o productos, cualquier barbero</li>
        </ol>
        <p class="text-muted small mb-0">
            Si ninguna encaja, esa línea no genera comisión. No se inventa un porcentaje
            por defecto: una comisión que aparece sola es una discusión asegurada a fin de mes.
        </p>
    </div>
</div>
@endsection
