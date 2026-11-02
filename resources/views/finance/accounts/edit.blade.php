@extends('layouts.app')

@section('title', 'Editar cuenta - Barbora')

@section('page')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 fw-bold mb-0"><i class="bi bi-bank"></i> Editar cuenta</h1>
    <a href="{{ route('accounts.show', $account) }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
</div>

<form action="{{ route('accounts.update', $account) }}" method="POST" style="max-width: 760px">
    @csrf
    @method('PUT')
    @include('finance.accounts._form', ['account' => $account])
    <button class="btn btn-primary mt-3"><i class="bi bi-save"></i> Guardar cambios</button>
</form>

@if(auth()->user()->hasPermissionInCompany('accounts.delete', $tenantCompany))
    <form action="{{ route('accounts.destroy', $account) }}" method="POST" class="mt-3"
          onsubmit="return confirm('¿Dar de baja esta cuenta?')">
        @csrf
        @method('DELETE')
        <button class="btn btn-link text-danger px-0"><i class="bi bi-trash"></i> Dar de baja la cuenta</button>
    </form>
@endif
@endsection
