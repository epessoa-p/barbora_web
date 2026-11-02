@extends('layouts.app')

@section('title', 'Nueva cuenta - Barbora')

@section('page')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 fw-bold mb-0"><i class="bi bi-bank"></i> Nueva cuenta</h1>
    <a href="{{ route('accounts.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
</div>

<form action="{{ route('accounts.store') }}" method="POST" style="max-width: 760px">
    @csrf
    @include('finance.accounts._form', ['account' => null])
    <button class="btn btn-primary mt-3"><i class="bi bi-save"></i> Crear cuenta</button>
</form>
@endsection
