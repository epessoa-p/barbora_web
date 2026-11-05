@extends('layouts.app')

@section('title', 'Categorías de gasto - Barbora')

{{--
    Las categorías, agrupadas por tipo (ExpenseCategory::TYPES):
      general    comida, pasajes, limpieza…
      basicos    servicios básicos: luz, agua, internet, alquiler, hosting…
                 se pagan cada mes o cada año (frequency)
      proveedor  pagos a proveedores (el gasto pide el proveedor)
      personal   pagos a personal (el gasto pide la persona)
--}}
@php
    $canCreate = auth()->user()->hasPermissionInCompany('expense_categories.create', $tenantCompany);
    $canEdit = auth()->user()->hasPermissionInCompany('expense_categories.edit', $tenantCompany);
    $canDelete = auth()->user()->hasPermissionInCompany('expense_categories.delete', $tenantCompany);
    $types = \App\Models\ExpenseCategory::TYPES;
    $frequencies = \App\Models\ExpenseCategory::FREQUENCIES;
    $groups = $categories->groupBy(fn ($c) => $c->group());
    $titles = ['general' => 'Gastos generales', 'basicos' => 'Servicios básicos', 'proveedor' => 'Pagos a proveedores', 'personal' => 'Pagos a personal'];
    $hints = [
        'general' => 'Comida, pasajes, limpieza… Salen en la pestaña Generales.',
        'basicos' => 'Lo que se paga cada mes o cada año. Salen en la pestaña Servicios básicos, con el control del mes.',
        'proveedor' => 'El gasto pide el proveedor; no hace falta elegir la categoría.',
        'personal' => 'El gasto pide la persona; no hace falta elegir la categoría.',
    ];
@endphp

@section('page')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h4 fw-bold mb-1"><i class="bi bi-tags"></i> Categorías de gasto</h1>
        <p class="text-muted mb-0">En qué se gasta. El tipo decide en qué pestaña de Gastos sale y qué pide al registrarlo.</p>
    </div>
</div>

@if($errors->any())
    <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif

@if($canCreate)
    <form action="{{ route('expense-categories.store') }}" method="POST" class="card border-0 shadow-sm mb-4 category-form">
        @csrf
        <div class="card-body p-3 row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small">Nueva categoría</label>
                <input type="text" name="name" class="form-control" maxlength="255" required placeholder="GAS, PUBLICIDAD, HOSTING…">
            </div>
            <div class="col-md-3">
                <label class="form-label small">Tipo</label>
                <select name="type" class="form-select category-type">
                    @foreach($types as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3 category-frequency d-none">
                <label class="form-label small">Se paga</label>
                <select name="frequency" class="form-select">
                    @foreach($frequencies as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2"><button class="btn btn-primary w-100"><i class="bi bi-plus-lg"></i> Añadir</button></div>
        </div>
    </form>
@endif

@foreach($titles as $groupKey => $title)
    @php $rows = $groups->get($groupKey, collect()); @endphp
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-0">
            <div class="p-3 border-bottom">
                <h6 class="fw-bold mb-0">{{ $title }} <span class="text-muted fw-normal small">({{ $rows->count() }})</span></h6>
                <small class="text-muted">{{ $hints[$groupKey] }}</small>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <tbody>
                        @forelse($rows as $category)
                            <tr class="{{ $category->active ? '' : 'opacity-50' }}">
                                @if($canEdit)
                                    <td class="ps-3">
                                        <form action="{{ route('expense-categories.update', $category) }}" method="POST" class="row g-2 align-items-center category-form">
                                            @csrf
                                            @method('PUT')
                                            <div class="col-md-4"><input type="text" name="name" class="form-control form-control-sm" value="{{ $category->name }}" required></div>
                                            <div class="col-md-3">
                                                <select name="type" class="form-select form-select-sm category-type">
                                                    @foreach($types as $value => $label)
                                                        <option value="{{ $value }}" {{ $category->group() === $value ? 'selected' : '' }}>{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-md-2 category-frequency {{ $category->recurring ? '' : 'd-none' }}">
                                                <select name="frequency" class="form-select form-select-sm">
                                                    @foreach($frequencies as $value => $label)
                                                        <option value="{{ $value }}" {{ ($category->frequency ?: 'mensual') === $value ? 'selected' : '' }}>{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-md-1 text-center small text-muted" title="Gastos registrados">{{ $category->expenses_count }}</div>
                                            <div class="col-md-1 text-center">
                                                <input class="form-check-input" type="checkbox" name="active" value="1" title="Activa" {{ $category->active ? 'checked' : '' }}>
                                            </div>
                                            <div class="col-md-1"><button class="btn btn-sm btn-outline-primary w-100">Guardar</button></div>
                                        </form>
                                    </td>
                                @else
                                    <td class="ps-3 fw-semibold">
                                        {{ $category->name }}
                                        @if($category->recurring)<span class="badge bg-light text-muted border">{{ $frequencies[$category->frequency] ?? 'Mensual' }}</span>@endif
                                    </td>
                                    <td class="text-center small text-muted">{{ $category->expenses_count }} gastos</td>
                                    <td class="text-center">{{ $category->active ? 'Activa' : 'De baja' }}</td>
                                @endif
                                <td class="text-end pe-3" style="width: 2rem">
                                    @if($canDelete)
                                        <form action="{{ route('expense-categories.destroy', $category) }}" method="POST"
                                              onsubmit="return confirm('¿Quitar esta categoría?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-link text-danger p-0"><i class="bi bi-trash"></i></button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td class="ps-3 text-muted small">Ninguna todavía.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endforeach
@endsection

@push('scripts')
<script>
// «Se paga» (mensual / anual) solo para los servicios básicos.
document.querySelectorAll('.category-form').forEach(form => {
    const type = form.querySelector('.category-type');
    const frequency = form.querySelector('.category-frequency');
    if (!type || !frequency) return;
    const refresh = () => frequency.classList.toggle('d-none', type.value !== 'basicos');
    type.addEventListener('change', refresh);
    refresh();
});
</script>
@endpush
