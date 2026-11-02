@extends('layouts.app')

@section('title', 'Proveedores - Barbora')

@section('page')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h4 fw-bold mb-1"><i class="bi bi-truck"></i> Proveedores</h1>
        <p class="text-muted mb-0">A quién se le compra mercadería o se le paga un gasto, y cuánto se le debe.</p>
    </div>
    @if(auth()->user()->hasPermissionInCompany('suppliers.create', $tenantCompany))
        <a href="{{ route('suppliers.create') }}" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Nuevo proveedor</a>
    @endif
</div>

<form method="GET" class="row g-2 mb-3">
    <div class="col-md-6"><input type="search" name="q" class="form-control" value="{{ request('q') }}" placeholder="Buscar por nombre, NIT o teléfono"></div>
    <div class="col-md-2"><button class="btn btn-outline-secondary w-100"><i class="bi bi-search"></i></button></div>
</form>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Proveedor</th>
                        <th>Contacto</th>
                        <th class="text-center">Compras</th>
                        <th class="text-end">Se le debe</th>
                        <th class="text-end pe-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($suppliers as $supplier)
                        @php($debt = (float) ($debts[$supplier->id] ?? 0))
                        <tr class="{{ $supplier->active ? '' : 'opacity-50' }}">
                            <td class="ps-3">
                                <div class="fw-semibold">{{ $supplier->name }}</div>
                                <small class="text-muted">{{ $supplier->tax_id ? 'NIT '.$supplier->tax_id : '' }}</small>
                            </td>
                            <td>
                                {{ $supplier->contact_name }}
                                <small class="text-muted d-block">{{ $supplier->phone }}</small>
                            </td>
                            <td class="text-center">{{ $supplier->purchases_count }}</td>
                            <td class="text-end fw-semibold {{ $debt > 0.004 ? 'text-danger' : 'text-muted' }}">
                                {{ \App\Support\Money::format($debt, $tenantCompany) }}
                            </td>
                            <td class="text-end pe-3">
                                <a href="{{ route('suppliers.show', $supplier) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-5">No hay proveedores registrados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
<div class="d-flex justify-content-center mt-3">{{ $suppliers->links() }}</div>
@endsection
