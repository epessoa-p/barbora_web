@extends('layouts.app')

@section('title', 'Nueva venta - Barbora')

@php
    $symbol = \App\Support\Money::symbol($tenantCompany);

    // Al cobrar una cita, la comanda arranca con sus servicios cargados.
    $preloaded = $appointment
        ? $appointment->services->map(fn ($s) => [
            'type' => 'servicio',
            'id' => $s->id,
            'name' => $s->name,
            'price' => (float) $s->pivot->price,
            'quantity' => 1,
        ])->values()
        : collect();

    // Alta rápida de cliente: el módulo en el plan y el permiso.
    $canCreateClient = (auth()->user()->is_super_admin || (bool) $tenantCompany?->planAllows('clientes'))
        && auth()->user()->hasPermissionInCompany('clients.create', $tenantCompany);

    // La pill elegida al abrir: el primer método que cuenta como efectivo.
    $cashSlugs = \App\Models\PaymentMethod::cashSlugs();
    $methodSlugs = array_keys(\App\Models\SalePayment::methods());
    $defaultMethod = collect($methodSlugs)->first(fn ($s) => in_array($s, $cashSlugs, true)) ?? ($methodSlugs[0] ?? null);
@endphp

@section('page')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h4 fw-bold mb-1"><i class="bi bi-cart-plus"></i> Nueva venta</h1>
        <p class="text-muted mb-0">
            @if($appointment)
                Cobrando la cita de {{ $appointment->client?->full_name }},
                {{ $appointment->starts_at->translatedFormat('d/m H:i') }}.
            @else
                Servicios y productos en una sola comanda.
            @endif
        </p>
    </div>
    <a href="{{ route('sales.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Volver
    </a>
</div>

<form action="{{ route('sales.store') }}" method="POST" id="sale-form">
    @csrf
    @if($appointment)
        <input type="hidden" name="appointment_id" value="{{ $appointment->id }}">
    @endif

    <div class="row g-4">
        {{-- Catálogo --}}
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <ul class="nav nav-pills mb-3" role="tablist">
                        <li class="nav-item">
                            <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#tab-services" type="button">
                                <i class="bi bi-scissors"></i> Servicios
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-products" type="button">
                                <i class="bi bi-box"></i> Productos
                            </button>
                        </li>
                    </ul>

                    <input type="search" class="form-control mb-3" id="catalog-search" placeholder="Buscar…">

                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="tab-services">
                            <div class="row g-2">
                                @forelse($services as $service)
                                    <div class="col-md-6 catalog-entry" data-name="{{ Str::lower($service->name) }}">
                                        <button type="button" class="catalog-card"
                                                data-type="servicio" data-id="{{ $service->id }}"
                                                data-name="{{ $service->name }}" data-price="{{ $service->price }}">
                                            <span class="catalog-name">{{ $service->name }}</span>
                                            <span class="catalog-meta">{{ $service->durationLabel() }}</span>
                                            <span class="catalog-price">{{ \App\Support\Money::format($service->price, $tenantCompany) }}</span>
                                        </button>
                                    </div>
                                @empty
                                    <p class="text-muted">No hay servicios activos.</p>
                                @endforelse
                            </div>
                        </div>

                        <div class="tab-pane fade" id="tab-products">
                            <div class="row g-2">
                                @forelse($products as $product)
                                    @php($available = $product->track_stock ? $product->totalStock() : null)
                                    <div class="col-md-6 catalog-entry" data-name="{{ Str::lower($product->name) }}">
                                        <button type="button" class="catalog-card"
                                                data-type="producto" data-id="{{ $product->id }}"
                                                data-name="{{ $product->name }}" data-price="{{ $product->sale_price }}"
                                                data-stock="{{ $available ?? '' }}"
                                                {{ $available !== null && $available <= 0 ? 'disabled' : '' }}>
                                            <span class="catalog-name">{{ $product->name }}</span>
                                            <span class="catalog-meta">
                                                @if($available === null)
                                                    Sin control de stock
                                                @elseif($available <= 0)
                                                    Sin existencias
                                                @else
                                                    {{ $product->formatQuantity($available) }} disponibles
                                                @endif
                                            </span>
                                            <span class="catalog-price">{{ \App\Support\Money::format($product->sale_price, $tenantCompany) }}</span>
                                        </button>
                                    </div>
                                @empty
                                    <p class="text-muted">No hay productos a la venta.</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Comanda --}}
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <div class="row g-2 mb-3">
                        <div class="col-12">
                            <label for="client_id" class="form-label">Cliente</label>
                            <select id="client_id" name="client_id" class="form-select">
                                <option value="">Cliente de mostrador</option>
                                @foreach($clients as $client)
                                    <option value="{{ $client->id }}"
                                            {{ (string) old('client_id', $appointment?->client_id) === (string) $client->id ? 'selected' : '' }}>
                                        {{ $client->full_name }}
                                    </option>
                                @endforeach
                            </select>
                            @if($canCreateClient)
                                <button type="button" class="btn btn-link btn-sm px-0" data-bs-toggle="modal" data-bs-target="#new-client-modal">
                                    <i class="bi bi-person-plus"></i> Nuevo cliente
                                </button>
                            @endif
                        </div>

                        <div class="{{ $branches->count() > 1 ? 'col-7' : 'col-12' }}">
                            <label for="personal_id" class="form-label">Atendió</label>
                            <select id="personal_id" name="personal_id" class="form-select">
                                <option value="">Sin asignar</option>
                                @foreach($staff as $person)
                                    <option value="{{ $person->id }}"
                                            {{ (string) old('personal_id', $appointment?->personal_id) === (string) $person->id ? 'selected' : '' }}>
                                        {{ $person->full_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- La de la cita que se cobra o, si no, la del usuario. --}}
                        @include('partials.branch-select', [
                            'branches' => $branches,
                            'selected' => $appointment?->branch_id ?? \App\Models\Branch::preferredIdFor(auth()->user(), $tenantCompany?->id),
                            'wrapper' => 'col-5',
                        ])
                    </div>

                    <h6 class="fw-bold mb-2">Comanda</h6>
                    <div id="cart-lines" class="mb-3">
                        <p class="text-muted small mb-0" id="cart-empty">
                            Toca un servicio o producto para añadirlo.
                        </p>
                    </div>

                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label for="discount" class="form-label">Descuento</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">{{ $symbol }}</span>
                                <input type="number" step="0.01" min="0" id="discount" name="discount"
                                       class="form-control" value="{{ old('discount', 0) }}">
                            </div>
                        </div>
                        <div class="col-6">
                            <label for="tip" class="form-label">Propina</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">{{ $symbol }}</span>
                                <input type="number" step="0.01" min="0" id="tip" name="tip"
                                       class="form-control" value="{{ old('tip', 0) }}">
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between py-1 border-top">
                        <span class="text-muted">Subtotal</span>
                        <span id="sum-subtotal">{{ $symbol }} 0.00</span>
                    </div>
                    <div class="d-flex justify-content-between py-2 fw-bold fs-5 border-top">
                        <span>Total</span>
                        <span id="sum-total">{{ $symbol }} 0.00</span>
                    </div>

                    <h6 class="fw-bold mt-3 mb-2">Pago</h6>
                    @if(empty(\App\Models\SalePayment::methods()))
                        <div class="alert alert-warning small py-2">
                            La barbería no tiene métodos de pago activos.
                            <a href="{{ route('payment-methods.index') }}">Actívalos en Métodos de pago</a>.
                        </div>
                    @endif
                    {{-- Un método: se elige con las pills y se lleva el total. Pago
                         mixto: un importe por cada método, que tiene que cuadrar. --}}
                    <div class="d-flex flex-wrap gap-2 mb-2" id="method-pills">
                        @foreach(\App\Models\SalePayment::methods() as $slug => $label)
                            <input type="radio" class="btn-check" name="method_pill" id="pill-{{ $slug }}" value="{{ $slug }}"
                                   autocomplete="off" {{ $slug === $defaultMethod ? 'checked' : '' }}>
                            <label class="btn btn-sm btn-outline-primary rounded-pill px-3" for="pill-{{ $slug }}">
                                <i class="bi {{ in_array($slug, $cashSlugs, true) ? 'bi-cash' : 'bi-credit-card' }}"></i>
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>

                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" role="switch" id="mixed-payment">
                        <label class="form-check-label small" for="mixed-payment">Pago mixto (varias formas de pago)</label>
                    </div>

                    <div id="mixed-lines" class="d-none mb-2">
                        @foreach(\App\Models\SalePayment::methods() as $slug => $label)
                            <div class="input-group input-group-sm mb-1">
                                <span class="input-group-text" style="min-width: 8rem">{{ $label }}</span>
                                <span class="input-group-text">{{ $symbol }}</span>
                                <input type="number" step="0.01" min="0" class="form-control mixed-amount"
                                       data-slug="{{ $slug }}" placeholder="0.00">
                            </div>
                        @endforeach
                    </div>

                    {{-- Lo que de verdad se envía: lo arma el script al cambiar algo. --}}
                    <div id="payment-lines"></div>

                    <div class="d-flex justify-content-between py-1 small d-none" id="pending-row">
                        <span class="text-muted">Pendiente</span>
                        <span id="sum-pending">{{ $symbol }} 0.00</span>
                    </div>

                    <div class="mb-3 mt-2">
                        <label for="notes" class="form-label">Notas</label>
                        <textarea id="notes" name="notes" rows="2" class="form-control form-control-sm">{{ old('notes') }}</textarea>
                    </div>

                    <button type="submit" class="btn btn-primary w-100" id="submit-sale" disabled>
                        <i class="bi bi-check-circle"></i> Cobrar
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>

@if($canCreateClient)
    @include('clients.partials.quick-create', ['selectId' => 'client_id'])
@endif
@endsection

@push('scripts')
<script>
(function () {
    const symbol = @json($symbol);
    const methods = @json(\App\Models\SalePayment::methods());
    const cart = [];

    const linesEl = document.getElementById('cart-lines');
    const emptyEl = document.getElementById('cart-empty');
    const paymentsEl = document.getElementById('payment-lines');
    const submitEl = document.getElementById('submit-sale');
    const discountEl = document.getElementById('discount');
    const tipEl = document.getElementById('tip');

    const money = (n) => symbol + ' ' + (Math.round(n * 100) / 100).toFixed(2);

    // ── Comanda ─────────────────────────────────────────────────────────────

    function addLine(type, id, name, price, stock) {
        const existing = cart.find((l) => l.type === type && l.id === id);

        if (existing) {
            // No dejar añadir más unidades de las que hay en el almacén: el
            // servidor lo rechazaría igualmente, mejor avisar aquí.
            if (existing.stock !== null && existing.quantity + 1 > existing.stock) return;
            existing.quantity++;
        } else {
            cart.push({ type, id, name, price, quantity: 1, stock });
        }

        render();
    }

    function render() {
        linesEl.querySelectorAll('.cart-line').forEach((el) => el.remove());
        emptyEl.classList.toggle('d-none', cart.length > 0);

        cart.forEach(function (line, index) {
            const row = document.createElement('div');
            row.className = 'cart-line d-flex align-items-center gap-2 py-2 border-bottom';
            row.innerHTML =
                '<div class="flex-fill">' +
                    '<div class="fw-semibold small">' + line.name + '</div>' +
                    '<div class="text-muted" style="font-size:.75rem">' + money(line.price) + ' c/u</div>' +
                '</div>' +
                '<input type="number" class="form-control form-control-sm qty" style="width:4.5rem" ' +
                    'min="0.01" step="1" value="' + line.quantity + '">' +
                '<div class="text-end fw-semibold small" style="width:5.5rem">' + money(line.price * line.quantity) + '</div>' +
                '<button type="button" class="btn btn-sm btn-link text-danger p-0 remove"><i class="bi bi-x-lg"></i></button>' +
                '<input type="hidden" name="items[' + index + '][type]" value="' + line.type + '">' +
                '<input type="hidden" name="items[' + index + '][id]" value="' + line.id + '">' +
                '<input type="hidden" name="items[' + index + '][quantity]" value="' + line.quantity + '">';

            row.querySelector('.qty').addEventListener('input', function () {
                let value = parseFloat(this.value) || 0;
                if (line.stock !== null && value > line.stock) { value = line.stock; this.value = value; }
                line.quantity = value;
                render();
            });

            row.querySelector('.remove').addEventListener('click', function () {
                cart.splice(index, 1);
                render();
            });

            linesEl.appendChild(row);
        });

        refreshTotals();
    }

    // ── Pagos ───────────────────────────────────────────────────────────────
    //
    // Sin pago mixto, la pill elegida se lleva el total. Con pago mixto, cada
    // método tiene su importe y la suma tiene que cuadrar con el total. En los
    // dos casos se envían payments[i][payment_method] / payments[i][amount].

    const mixedEl = document.getElementById('mixed-payment');
    const mixedLinesEl = document.getElementById('mixed-lines');
    const pillsEl = document.getElementById('method-pills');
    const mixedInputs = Array.from(document.querySelectorAll('.mixed-amount'));
    let lastTotal = 0;

    function selectedMethod() {
        return pillsEl.querySelector('input:checked')?.value || Object.keys(methods)[0];
    }

    function writePayments(lines) {
        paymentsEl.innerHTML = '';
        lines.forEach(function (line, i) {
            ['payment_method', 'amount'].forEach(function (field) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'payments[' + i + '][' + field + ']';
                input.value = field === 'amount' ? line.amount.toFixed(2) : line.method;
                paymentsEl.appendChild(input);
            });
        });
    }

    function refreshTotals() {
        const subtotal = cart.reduce((sum, l) => sum + l.price * l.quantity, 0);
        const discount = parseFloat(discountEl.value) || 0;
        const tip = parseFloat(tipEl.value) || 0;
        const total = subtotal - discount + tip;
        lastTotal = total;

        document.getElementById('sum-subtotal').textContent = money(subtotal);
        document.getElementById('sum-total').textContent = money(total);

        const mixed = mixedEl.checked;
        mixedLinesEl.classList.toggle('d-none', !mixed);
        pillsEl.classList.toggle('d-none', mixed);
        document.getElementById('pending-row').classList.toggle('d-none', !mixed);

        if (!mixed) {
            writePayments([{ method: selectedMethod(), amount: total }]);
            submitEl.disabled = cart.length === 0 || total <= 0;
            return;
        }

        const lines = mixedInputs
            .map((el) => ({ method: el.dataset.slug, amount: parseFloat(el.value) || 0 }))
            .filter((l) => l.amount > 0);
        writePayments(lines);

        const paid = lines.reduce((sum, l) => sum + l.amount, 0);
        const pending = total - paid;

        const pendingEl = document.getElementById('sum-pending');
        pendingEl.textContent = money(Math.abs(pending));
        pendingEl.className = Math.abs(pending) < 0.01 ? 'text-success' : (pending > 0 ? 'text-danger' : 'text-info');
        document.getElementById('pending-row').querySelector('.text-muted').textContent =
            pending < -0.005 ? 'Sobra' : 'Pendiente';

        submitEl.disabled = cart.length === 0 || total <= 0 || lines.length === 0 || Math.abs(pending) >= 0.01;
    }

    // Al activar el mixto se arranca con todo en el método elegido: lo normal
    // es repartir a partir de ahí.
    mixedEl.addEventListener('change', function () {
        if (mixedEl.checked) {
            const total = lastTotal;
            mixedInputs.forEach(function (el) {
                el.value = el.dataset.slug === selectedMethod() ? total.toFixed(2) : '';
            });
        }
        refreshTotals();
    });
    mixedInputs.forEach((el) => el.addEventListener('input', refreshTotals));
    pillsEl.addEventListener('change', refreshTotals);

    // ── Enganches ───────────────────────────────────────────────────────────

    document.querySelectorAll('.catalog-card').forEach(function (card) {
        card.addEventListener('click', function () {
            const stock = card.dataset.stock === '' ? null : parseFloat(card.dataset.stock);
            addLine(card.dataset.type, parseInt(card.dataset.id, 10), card.dataset.name,
                    parseFloat(card.dataset.price), stock);
        });
    });

    document.getElementById('catalog-search').addEventListener('input', function () {
        const term = this.value.toLowerCase().trim();
        document.querySelectorAll('.catalog-entry').forEach(function (entry) {
            entry.classList.toggle('d-none', term !== '' && !entry.dataset.name.includes(term));
        });
    });

    discountEl.addEventListener('input', refreshTotals);
    tipEl.addEventListener('input', refreshTotals);

    @foreach($preloaded as $line)
        addLine(@json($line['type']), {{ $line['id'] }}, @json($line['name']), {{ $line['price'] }}, null);
    @endforeach

    render();
})();
</script>
@endpush
