@extends('layouts.app')

@section('title', 'Nuevo proveedor - Barbora')

@section('page')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 fw-bold mb-0"><i class="bi bi-truck"></i> Nuevo proveedor</h1>
    <a href="{{ route('suppliers.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
</div>

<form action="{{ route('suppliers.store') }}" method="POST" style="max-width: 860px">
    @csrf
    @include('purchases.suppliers._form', ['supplier' => null])
    <button class="btn btn-primary mt-3"><i class="bi bi-save"></i> Registrar proveedor</button>
</form>
@endsection
