@extends('layouts.app')

@section('title', 'Editar proveedor - Barbora')

@section('page')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 fw-bold mb-0"><i class="bi bi-truck"></i> Editar proveedor</h1>
    <a href="{{ route('suppliers.show', $supplier) }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
</div>

<form action="{{ route('suppliers.update', $supplier) }}" method="POST" style="max-width: 860px">
    @csrf
    @method('PUT')
    @include('purchases.suppliers._form', ['supplier' => $supplier])
    <button class="btn btn-primary mt-3"><i class="bi bi-save"></i> Guardar cambios</button>
</form>

@if(auth()->user()->hasPermissionInCompany('suppliers.delete', $tenantCompany))
    <form action="{{ route('suppliers.destroy', $supplier) }}" method="POST" class="mt-3" onsubmit="return confirm('¿Dar de baja este proveedor?')">
        @csrf
        @method('DELETE')
        <button class="btn btn-link text-danger px-0"><i class="bi bi-trash"></i> Dar de baja</button>
    </form>
@endif
@endsection
