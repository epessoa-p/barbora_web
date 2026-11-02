@extends('layouts.app')

@section('title', 'Nueva compra - Barbora')

@php
    $symbol = \App\Support\Money::symbol($tenantCompany);
    $oldItems = old('items', [['product_id' => '', 'quantity' => 1, 'unit_cost' => '']]);
@endphp

@section('page')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h4 fw-bold mb-1"><i class="bi bi-bag-plus"></i> Nueva compra</h1>
        <p class="text-muted mb-0">Lo comprado entra al almacén al registrar. Se paga ahora, en parte o a crédito.</p>
    </div>
    <a href="{{ route('purchases.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
</div>

@if($errors->any())
    <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif

<form action="{{ route('purchases.store') }}" method="POST" class="row g-4" id="purchase-form">
    @csrf
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Proveedor</label>
                        <select name="supplier_id" class="form-select">
                            <option value="">Sin proveedor</option>
                            @foreach($suppliers as $supplier)
                                <option value="{{ $supplier->id }}" {{ (string) old('supplier_id', request('supplier')) === (string) $supplier->id ? 'selected' : '' }}>{{ $supplier->name }}</option>
                            @endforeach
                        </select>
                        @if(auth()->user()->hasPermissionInCompany('suppliers.create', $tenantCompany))
                            <small><a href="{{ route('suppliers.create') }}" target="_blank">Nuevo proveedor</a></small>
                        @endif
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Entra al almacén <span class="text-danger">*</span></label>
                        <select name="warehouse_id" class="form-select" required>
                            @foreach($warehouses as $warehouse)
                                <option value="{{ $warehouse->id }}" {{ (string) old('warehouse_id', $defaultWarehouse) === (string) $warehouse->id ? 'selected' : '' }}>{{ $warehouse->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Fecha <span class="text-danger">*</span></label>
                        <input type="date" name="purchased_on" class="form-control" required max="{{ now()->toDateString() }}" value="{{ old('purchased_on', now()->toDateString()) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Nº de factura</label>
                        <input type="text" name="invoice_number" class="form-control" maxlength="60" value="{{ old('invoice_number') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Vence (si es a crédito)</label>
                        <input type="date" name="due_on" class="form-control" value="{{ old('due_on') }}">
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-3">Productos</h6>
                <div class="table-responsive">
                    <table class="table align-middle mb-2">
                        <thead class="table-light">
                            <tr>
                                <th>Producto</th>
                                <th style="width: 7rem">Cantidad</th>
                                <th style="width: 9rem">Costo unitario</th>
                                <th class="text-end" style="width: 8rem">Total</th>
                                <th style="width: 2rem"></th>
                            </tr>
                        </thead>
                        <tbody id="items"></tbody>
                    </table>
                </div>
                <button type="button" class="btn btn-sm btn-light border" id="add-item"><i class="bi bi-plus-lg"></i> Añadir producto</button>
                <small class="text-muted d-block mt-2">El costo del producto se actualiza con el de esta compra.</small>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between py-1"><span class="text-muted">Subtotal</span><span id="sum-subtotal">{{ $symbol }} 0.00</span></div>
                <div class="d-flex justify-content-between align-items-center py-1">
                    <span class="text-muted">Descuento</span>
                    <input type="number" step="0.01" min="0" name="discount" id="discount" class="form-control form-control-sm text-end" style="width: 7rem" value="{{ old('discount', 0) }}">
                </div>
                <div class="d-flex justify-content-between py-2 fw-bold fs-5 border-top"><span>Total</span><span id="sum-total">{{ $symbol }} 0.00</span></div>

                <label class="form-label fw-semibold mt-3">¿Cuánto pagas ahora?</label>
                <div class="btn-group w-100 mb-2" role="group">
                    <input type="radio" class="btn-check" name="pay_mode" id="pay-all" value="all" checked>
                    <label class="btn btn-outline-primary btn-sm" for="pay-all">Todo</label>
                    <input type="radio" class="btn-check" name="pay_mode" id="pay-part" value="part">
                    <label class="btn btn-outline-primary btn-sm" for="pay-part">Una parte</label>
                    <input type="radio" class="btn-check" name="pay_mode" id="pay-none" value="none">
                    <label class="btn btn-outline-primary btn-sm" for="pay-none">A crédito</label>
                </div>
                <div class="input-group mb-2 d-none" id="pay-amount-group">
                    <span class="input-group-text">{{ $symbol }}</span>
                    <input type="number" step="0.01" min="0" class="form-control" id="pay-amount-input" placeholder="Importe que pagas ahora">
                </div>
                <input type="hidden" name="pay_amount" id="pay_amount" value="{{ old('pay_amount', 0) }}">
                <div class="small text-muted" id="debt-hint"></div>
            </div>
        </div>

        <div class="card border-0 shadow-sm" id="source-card">
            <div class="card-body p-4">
                @include('finance.partials.payment-source')
            </div>
        </div>

        <textarea name="notes" rows="2" class="form-control mt-3" placeholder="Notas (opcional)">{{ old('notes') }}</textarea>
        <button class="btn btn-primary w-100 mt-3" id="submit-purchase"><i class="bi bi-check-circle"></i> Registrar compra</button>
    </div>
</form>
@endsection

@push('scripts')
<script>
(function () {
    const symbol = @json($symbol);
    const products = @json($productOptions);
    const itemsEl = document.getElementById('items');
    const discountEl = document.getElementById('discount');
    const money = (n) => symbol + ' ' + (Math.round(n * 100) / 100).toFixed(2);
    let total = 0;

    function productOptions(selected) {
        return '<option value="">Elegir producto…</option>' + products.map((p) =>
            '<option value="' + p.id + '" data-cost="' + p.cost + '"' + (String(selected) === String(p.id) ? ' selected' : '') + '>' +
            p.name.replace(/</g, '&lt;') + (p.stock ? '' : ' (sin control de stock)') + '</option>').join('');
    }

    function addRow(item) {
        const row = document.createElement('tr');
        row.innerHTML =
            '<td><select class="form-select form-select-sm product" required>' + productOptions(item.product_id) + '</select></td>' +
            '<td><input type="number" step="0.01" min="0.01" class="form-control form-control-sm qty" value="' + (item.quantity || 1) + '" required></td>' +
            '<td><input type="number" step="0.01" min="0" class="form-control form-control-sm cost" value="' + (item.unit_cost ?? '') + '" required></td>' +
            '<td class="text-end line-total">' + money(0) + '</td>' +
            '<td><button type="button" class="btn btn-sm btn-link text-danger p-0 remove"><i class="bi bi-x-lg"></i></button></td>';

        // Al elegir producto se propone su último costo.
        row.querySelector('.product').addEventListener('change', function () {
            const cost = this.selectedOptions[0]?.dataset.cost;
            if (cost !== undefined && row.querySelector('.cost').value === '') row.querySelector('.cost').value = parseFloat(cost).toFixed(2);
            refresh();
        });
        row.querySelectorAll('.qty, .cost').forEach((el) => el.addEventListener('input', refresh));
        row.querySelector('.remove').addEventListener('click', function () { row.remove(); refresh(); });
        itemsEl.appendChild(row);
    }

    // Los nombres items[i][…] se reescriben al cambiar algo: así nunca quedan huecos.
    function refresh() {
        let subtotal = 0;
        Array.from(itemsEl.children).forEach(function (row, i) {
            row.querySelector('.product').name = 'items[' + i + '][product_id]';
            row.querySelector('.qty').name = 'items[' + i + '][quantity]';
            row.querySelector('.cost').name = 'items[' + i + '][unit_cost]';
            const line = (parseFloat(row.querySelector('.qty').value) || 0) * (parseFloat(row.querySelector('.cost').value) || 0);
            row.querySelector('.line-total').textContent = money(line);
            subtotal += line;
        });
        total = Math.max(0, subtotal - (parseFloat(discountEl.value) || 0));
        document.getElementById('sum-subtotal').textContent = money(subtotal);
        document.getElementById('sum-total').textContent = money(total);
        refreshPayment();
    }

    // Todo / una parte / a crédito. El origen del dinero solo hace falta si se paga algo.
    function refreshPayment() {
        const mode = document.querySelector('input[name="pay_mode"]:checked').value;
        document.getElementById('pay-amount-group').classList.toggle('d-none', mode !== 'part');
        const part = Math.min(parseFloat(document.getElementById('pay-amount-input').value) || 0, total);
        const pay = mode === 'all' ? total : (mode === 'part' ? part : 0);
        document.getElementById('pay_amount').value = pay.toFixed(2);
        document.getElementById('source-card').classList.toggle('d-none', pay <= 0);
        const debt = total - pay;
        document.getElementById('debt-hint').textContent = debt > 0.004
            ? 'Queda como deuda con el proveedor: ' + money(debt) + '.'
            : 'Se paga completa.';
    }

    document.getElementById('add-item').addEventListener('click', () => { addRow({}); refresh(); });
    discountEl.addEventListener('input', refresh);
    document.querySelectorAll('input[name="pay_mode"]').forEach((el) => el.addEventListener('change', refreshPayment));
    document.getElementById('pay-amount-input').addEventListener('input', refreshPayment);

    @json($oldItems).forEach(addRow);
    refresh();
})();
</script>
@endpush
