@extends('layouts.app')

@section('title', 'Nuevo gasto - Barbora')

@section('page')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h4 fw-bold mb-1"><i class="bi bi-receipt-cutoff"></i> Nuevo gasto</h1>
        <p class="text-muted mb-0">Elige en qué se gastó y de dónde sale el dinero.</p>
    </div>
    <a href="{{ route('expenses.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
</div>

@if($errors->any())
    <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif

<form action="{{ route('expenses.store') }}" method="POST" class="row g-4">
    @csrf
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Categoría <span class="text-danger">*</span></label>
                        <select name="expense_category_id" id="expense_category_id" class="form-select" required>
                            <option value="">Elegir…</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" data-payee="{{ $category->payee }}"
                                    {{ (string) old('expense_category_id') === (string) $category->id ? 'selected' : '' }}>
                                    {{ $category->name }}
                                </option>
                            @endforeach
                        </select>
                        @if(auth()->user()->hasPermissionInCompany('expense_categories.create', $tenantCompany))
                            <small><a href="{{ route('expense-categories.index') }}" target="_blank">Administrar categorías</a></small>
                        @endif
                    </div>

                    {{-- A quién se le paga: según la categoría, un proveedor o alguien del personal. --}}
                    <div class="col-md-6 d-none" id="payee-supplier">
                        <label class="form-label">Proveedor</label>
                        <select name="supplier_id" class="form-select">
                            <option value="">Sin especificar</option>
                            @foreach($suppliers as $supplier)
                                <option value="{{ $supplier->id }}" {{ (string) old('supplier_id') === (string) $supplier->id ? 'selected' : '' }}>{{ $supplier->name }}</option>
                            @endforeach
                        </select>
                        @if(auth()->user()->hasPermissionInCompany('suppliers.create', $tenantCompany))
                            <small><a href="{{ route('suppliers.create') }}" target="_blank">Nuevo proveedor</a></small>
                        @endif
                    </div>
                    <div class="col-md-6 d-none" id="payee-personal">
                        <label class="form-label">Personal</label>
                        <select name="personal_id" class="form-select">
                            <option value="">Sin especificar</option>
                            @foreach($staff as $person)
                                <option value="{{ $person->id }}" {{ (string) old('personal_id') === (string) $person->id ? 'selected' : '' }}>{{ $person->full_name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Descripción <span class="text-danger">*</span></label>
                        <input type="text" name="description" class="form-control" maxlength="255" required data-no-upper
                               value="{{ old('description') }}" placeholder="Alquiler de noviembre, almuerzo del equipo, pasaje a la central…">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Importe <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">{{ \App\Support\Money::symbol($tenantCompany) }}</span>
                            <input type="number" step="0.01" min="0.01" name="amount" class="form-control" required value="{{ old('amount') }}">
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

@push('scripts')
<script>
(function () {
    // Muestra el selector de proveedor o de personal según la categoría.
    const category = document.getElementById('expense_category_id');
    function refresh() {
        const payee = category.selectedOptions[0]?.dataset.payee;
        document.getElementById('payee-supplier').classList.toggle('d-none', payee !== 'proveedor');
        document.getElementById('payee-personal').classList.toggle('d-none', payee !== 'personal');
    }
    category.addEventListener('change', refresh);
    refresh();
})();
</script>
@endpush
