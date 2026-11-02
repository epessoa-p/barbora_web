@extends('layouts.app')

@section('title', 'Categorías de gasto - Barbora')

@php
    $canCreate = auth()->user()->hasPermissionInCompany('expense_categories.create', $tenantCompany);
    $canEdit = auth()->user()->hasPermissionInCompany('expense_categories.edit', $tenantCompany);
    $canDelete = auth()->user()->hasPermissionInCompany('expense_categories.delete', $tenantCompany);
@endphp

@section('page')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h4 fw-bold mb-1"><i class="bi bi-tags"></i> Categorías de gasto</h1>
        <p class="text-muted mb-0">En qué se gasta. «A quién se paga» decide si el gasto pide un proveedor o alguien del personal.</p>
    </div>
</div>

@if($errors->any())
    <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif

@if($canCreate)
    <form action="{{ route('expense-categories.store') }}" method="POST" class="card border-0 shadow-sm mb-4">
        @csrf
        <div class="card-body p-3 row g-2 align-items-end">
            <div class="col-md-6">
                <label class="form-label small">Nueva categoría</label>
                <input type="text" name="name" class="form-control" maxlength="255" required placeholder="GAS, PUBLICIDAD, CAPACITACIÓN…">
            </div>
            <div class="col-md-4">
                <label class="form-label small">A quién se paga</label>
                <select name="payee" class="form-select">
                    @foreach(\App\Models\ExpenseCategory::PAYEES as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2"><button class="btn btn-primary w-100"><i class="bi bi-plus-lg"></i> Añadir</button></div>
        </div>
    </form>
@endif

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Categoría</th>
                        <th>A quién se paga</th>
                        <th class="text-center">Gastos</th>
                        <th class="text-center">Activa</th>
                        <th class="text-end pe-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($categories as $category)
                        <tr class="{{ $category->active ? '' : 'opacity-50' }}">
                            @if($canEdit)
                                <td class="ps-3" colspan="4">
                                    <form action="{{ route('expense-categories.update', $category) }}" method="POST" class="row g-2 align-items-center">
                                        @csrf
                                        @method('PUT')
                                        <div class="col-md-5"><input type="text" name="name" class="form-control form-control-sm" value="{{ $category->name }}" required></div>
                                        <div class="col-md-3">
                                            <select name="payee" class="form-select form-select-sm">
                                                @foreach(\App\Models\ExpenseCategory::PAYEES as $value => $label)
                                                    <option value="{{ $value }}" {{ $category->payee === $value ? 'selected' : '' }}>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-1 text-center small text-muted">{{ $category->expenses_count }}</div>
                                        <div class="col-md-1 text-center">
                                            <input class="form-check-input" type="checkbox" name="active" value="1" {{ $category->active ? 'checked' : '' }}>
                                        </div>
                                        <div class="col-md-2"><button class="btn btn-sm btn-outline-primary w-100">Guardar</button></div>
                                    </form>
                                </td>
                            @else
                                <td class="ps-3 fw-semibold">{{ $category->name }}</td>
                                <td>{{ $category->payeeLabel() }}</td>
                                <td class="text-center">{{ $category->expenses_count }}</td>
                                <td class="text-center">{{ $category->active ? 'Sí' : 'No' }}</td>
                            @endif
                            <td class="text-end pe-3">
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
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
