@extends('layouts.app')

@section('title', 'Gastos - Barbora')

@section('page')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h4 fw-bold mb-1"><i class="bi bi-receipt-cutoff"></i> Gastos</h1>
        <p class="text-muted mb-0">Pagos a proveedores y personal, servicios, comida, pasajes… Salen de una cuenta o de la caja.</p>
    </div>
    @if(auth()->user()->hasPermissionInCompany('expenses.create', $tenantCompany) && $group !== 'todos')
        <a href="{{ route('expenses.create', ['group' => $group]) }}" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i>
            {{ ['general' => 'Nuevo gasto', 'proveedor' => 'Pago a proveedor', 'personal' => 'Pago a personal', 'basicos' => 'Pago de servicio'][$group] }}
        </a>
    @endif
</div>

{{-- Cada tipo de gasto en su pestaña; «Todos» es el resumen. --}}
<ul class="nav nav-pills mb-3">
    @foreach(['general' => ['Generales', 'bi-receipt'], 'proveedor' => ['Proveedores', 'bi-truck'], 'personal' => ['Personal', 'bi-person-badge'], 'basicos' => ['Servicios básicos', 'bi-house-gear'], 'todos' => ['Todos', 'bi-collection']] as $key => [$label, $icon])
        <li class="nav-item">
            <a href="{{ route('expenses.index', ['group' => $key]) }}" class="nav-link {{ $group === $key ? 'active' : '' }}"><i class="bi {{ $icon }}"></i> {{ $label }}</a>
        </li>
    @endforeach
</ul>

{{-- Personal: las comisiones sin pagar, para pagarlas desde aquí. --}}
@if($owed !== null)
    @include('commissions.partials.owed-card', ['owed' => $owed, 'owedSummary' => \App\Support\PendingCommissions::summary($owed)])
@endif

{{-- Servicios básicos: cuáles se pagaron este mes y cuáles faltan. --}}
@if($basics)
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <a href="{{ route('expenses.index', ['group' => 'basicos', 'month' => $basics['previous']]) }}" class="btn btn-light border btn-sm"><i class="bi bi-chevron-left"></i></a>
                <div class="text-center">
                    <div class="fw-bold">{{ $basics['label'] }}</div>
                    <div class="small text-muted">{{ $basics['paid_count'] }} pagados · {{ $basics['pending_count'] }} pendientes · {{ \App\Support\Money::format($basics['total_paid'], $tenantCompany) }}</div>
                </div>
                @if($basics['next'])
                    <a href="{{ route('expenses.index', ['group' => 'basicos', 'month' => $basics['next']]) }}" class="btn btn-light border btn-sm"><i class="bi bi-chevron-right"></i></a>
                @else
                    <span class="btn btn-sm invisible"><i class="bi bi-chevron-right"></i></span>
                @endif
            </div>
            <div class="row g-2">
                @forelse($basics['services'] as $s)
                    @php $paid = $s['status'] === 'pagado'; @endphp
                    <div class="col-md-6 col-xl-4">
                        <div class="border rounded p-2 d-flex align-items-center gap-2 h-100 {{ $paid ? '' : 'border-warning' }}">
                            <i class="bi {{ $paid ? 'bi-check-circle-fill text-success' : 'bi-clock text-warning' }} fs-5"></i>
                            <div class="flex-fill">
                                <div class="fw-semibold">{{ $s['name'] }} @if($s['frequency'] === 'anual')<span class="badge bg-light text-muted border">Anual</span>@endif</div>
                                <div class="small text-muted">
                                    @if($paid)
                                        Pagado {{ \App\Support\Money::format($s['paid'], $tenantCompany) }}
                                        @if($s['next_due_on']) · vuelve el {{ \Carbon\Carbon::parse($s['next_due_on'])->format('d/m/Y') }} @endif
                                    @else
                                        Pendiente @if($s['last_amount'] !== null) · último {{ \App\Support\Money::format($s['last_amount'], $tenantCompany) }} @endif
                                    @endif
                                </div>
                            </div>
                            @if(! $paid && auth()->user()->hasPermissionInCompany('expenses.create', $tenantCompany))
                                <a href="{{ route('expenses.create', ['group' => 'basicos', 'category' => $s['id'], 'amount' => $s['last_amount']]) }}" class="btn btn-sm btn-outline-primary">Registrar</a>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="col-12 text-muted small">No hay servicios básicos. Créalos en <a href="{{ route('expense-categories.index') }}">Categorías de gasto</a> con el tipo «Servicio básico».</div>
                @endforelse
            </div>
        </div>
    </div>
@endif

<form method="GET" class="row g-2 mb-3">
    <input type="hidden" name="group" value="{{ $group }}">
    <div class="col-md-2"><input type="date" name="from" class="form-control" value="{{ request('from') }}" title="Desde"></div>
    <div class="col-md-2"><input type="date" name="to" class="form-control" value="{{ request('to') }}" title="Hasta"></div>
    <div class="col-md-3">
        <select name="category" class="form-select">
            <option value="">Todas las categorías</option>
            @foreach($categories as $category)
                <option value="{{ $category->id }}" {{ (string) request('category') === (string) $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3">
        <select name="source" class="form-select">
            <option value="">Cuentas y caja</option>
            <option value="account" {{ request('source') === 'account' ? 'selected' : '' }}>Solo de cuentas</option>
            <option value="cash" {{ request('source') === 'cash' ? 'selected' : '' }}>Solo de caja</option>
        </select>
    </div>
    <div class="col-md-2 d-flex gap-2">
        <button class="btn btn-outline-secondary flex-fill"><i class="bi bi-funnel"></i></button>
        @if(request()->hasAny(['from', 'to', 'category', 'source']))
            <a href="{{ route('expenses.index', ['group' => $group]) }}" class="btn btn-light border">Limpiar</a>
        @endif
    </div>
</form>

<div class="alert alert-light border d-flex justify-content-between align-items-center">
    <span class="text-muted">Total gastado{{ request()->hasAny(['from', 'to', 'category', 'source']) ? ' (con el filtro)' : '' }}</span>
    <span class="fw-bold fs-5">{{ \App\Support\Money::format($total, $tenantCompany) }}</span>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Nº</th>
                        <th>Fecha</th>
                        <th>Gasto</th>
                        <th>Salió de</th>
                        <th class="text-end">Importe</th>
                        <th class="text-end pe-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($expenses as $expense)
                        <tr class="{{ $expense->isCancelled() ? 'opacity-50' : '' }}">
                            <td class="ps-3"><code>{{ $expense->number }}</code></td>
                            <td class="text-nowrap">{{ $expense->spent_on->format('d/m/Y') }}</td>
                            <td>
                                <div class="fw-semibold">{{ $expense->description }}</div>
                                <small class="text-muted">
                                    {{ $expense->category?->name }}
                                    @if($expense->payeeName()) · {{ $expense->payeeName() }} @endif
                                </small>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">
                                    <i class="bi {{ $expense->source === 'cash' ? 'bi-cash-coin' : 'bi-bank' }}"></i> {{ $expense->sourceLabel() }}
                                </span>
                            </td>
                            <td class="text-end fw-semibold text-nowrap">
                                {{ \App\Support\Money::format($expense->amount, $tenantCompany) }}
                                @if($expense->isCancelled())<span class="badge bg-secondary d-block mt-1">Anulado</span>@endif
                            </td>
                            <td class="text-end pe-3">
                                <a href="{{ route('expenses.show', $expense) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-5">No hay gastos registrados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
<div class="d-flex justify-content-center mt-3">{{ $expenses->links() }}</div>
@endsection
