@extends('layouts.app')

@section('title', 'Nuevo gasto - Barbora')

{{--
    El alta cambia según la pestaña de la que se llega ($group):
      general    categoría general (comida, pasajes…) y descripción
      basicos    el servicio (luz, agua, hosting…); la descripción se completa sola
      proveedor  el proveedor; la categoría va sola («PAGO A PROVEEDORES»)
      personal   la persona; la categoría va sola («PAGO A PERSONAL»)
--}}
@php
    $titles = [
        'general' => ['Nuevo gasto', 'Comida, pasajes, limpieza… y de dónde sale el dinero.'],
        'basicos' => ['Pago de servicio básico', 'Luz, agua, internet, alquiler, hosting… lo que se paga cada mes o cada año.'],
        'proveedor' => ['Pago a proveedor', 'A quién se le paga, cuánto y de dónde sale el dinero.'],
        'personal' => ['Pago a personal', 'A quién se le paga, cuánto y de dónde sale el dinero.'],
    ];
    [$title, $subtitle] = $titles[$group];
    $payeeGroup = in_array($group, ['proveedor', 'personal'], true);
    $selected = old('expense_category_id', $selectedCategory);
@endphp

@section('page')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h4 fw-bold mb-1"><i class="bi bi-receipt-cutoff"></i> {{ $title }}</h1>
        <p class="text-muted mb-0">{{ $subtitle }}</p>
    </div>
    <a href="{{ route('expenses.index', ['group' => $group]) }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
</div>

@if($errors->any())
    <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif

<form action="{{ route('expenses.store') }}" method="POST" class="row g-4">
    @csrf
    <input type="hidden" name="group" value="{{ $group }}">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <div class="row g-3">
                    @if($group === 'proveedor')
                        <div class="col-md-6">
                            <label class="form-label">Proveedor <span class="text-danger">*</span></label>
                            <select name="supplier_id" class="form-select" required>
                                <option value="">Elegir…</option>
                                @foreach($suppliers as $supplier)
                                    <option value="{{ $supplier->id }}" {{ (string) old('supplier_id') === (string) $supplier->id ? 'selected' : '' }}>{{ $supplier->name }}</option>
                                @endforeach
                            </select>
                            @if(auth()->user()->hasPermissionInCompany('suppliers.create', $tenantCompany))
                                <small><a href="{{ route('suppliers.create') }}" target="_blank">Nuevo proveedor</a></small>
                            @endif
                        </div>
                    @elseif($group === 'personal')
                        <div class="col-md-6">
                            <label class="form-label">Personal <span class="text-danger">*</span></label>
                            <select name="personal_id" class="form-select" required>
                                <option value="">Elegir…</option>
                                @foreach($staff as $person)
                                    <option value="{{ $person->id }}" {{ (string) old('personal_id') === (string) $person->id ? 'selected' : '' }}>{{ $person->full_name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    {{-- A proveedor o personal, la categoría solo se elige si hay más de una. --}}
                    @if(! $payeeGroup || $categories->count() > 1)
                        <div class="col-md-6">
                            <label class="form-label">
                                {{ $group === 'basicos' ? 'Servicio' : ($payeeGroup ? 'Concepto' : 'Categoría') }}
                                @unless($payeeGroup)<span class="text-danger">*</span>@endunless
                            </label>
                            <select name="expense_category_id" class="form-select" {{ $payeeGroup ? '' : 'required' }}>
                                <option value="">{{ $payeeGroup ? 'El habitual' : 'Elegir…' }}</option>
                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}" {{ (string) $selected === (string) $category->id ? 'selected' : '' }}>
                                        {{ $category->name }}@if($category->isAnnual()) (anual)@endif
                                    </option>
                                @endforeach
                            </select>
                            @if(auth()->user()->hasPermissionInCompany('expense_categories.create', $tenantCompany))
                                <small><a href="{{ route('expense-categories.index') }}" target="_blank">{{ $group === 'basicos' ? 'Administrar servicios' : 'Administrar categorías' }}</a></small>
                            @endif
                        </div>
                    @endif

                    <div class="col-12">
                        <label class="form-label">Descripción @if($group === 'general')<span class="text-danger">*</span>@else<span class="text-muted small">(opcional)</span>@endif</label>
                        <input type="text" name="description" class="form-control" maxlength="255" {{ $group === 'general' ? 'required' : '' }} data-no-upper
                               value="{{ old('description') }}"
                               placeholder="{{ $group === 'general' ? 'Almuerzo del equipo, pasaje a la central…' : 'Si la dejas vacía: «'.($group === 'basicos' ? 'LUZ · noviembre 2026' : 'Pago a …').'»' }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Importe <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">{{ \App\Support\Money::symbol($tenantCompany) }}</span>
                            <input type="number" step="0.01" min="0.01" name="amount" class="form-control" required value="{{ old('amount', $suggestedAmount) }}">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Fecha <span class="text-danger">*</span></label>
                        <input type="date" name="spent_on" class="form-control" required max="{{ now()->toDateString() }}" value="{{ old('spent_on', now()->toDateString()) }}">
                    </div>
                    @include('partials.branch-select', [
                        'branches' => $branches,
                        'selected' => $preferredBranchId,
                        'placeholder' => 'La de la caja o la cuenta',
                    ])
                    <div class="col-md-6">
                        <label class="form-label">Referencia</label>
                        <input type="text" name="reference" class="form-control" maxlength="255" value="{{ old('reference') }}" placeholder="Nº de factura o recibo">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Notas</label>
                        <textarea name="notes" rows="2" class="form-control">{{ old('notes') }}</textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                @include('finance.partials.payment-source')
            </div>
        </div>
        <button class="btn btn-primary w-100 mt-3"><i class="bi bi-check-circle"></i> Registrar gasto</button>
    </div>
</form>
@endsection
