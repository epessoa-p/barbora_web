@extends('layouts.app')

@section('title', 'Editar Almacén - Barbora')

@section('page')
<h1 class="h4 fw-bold"><i class="bi bi-pencil"></i> Editar Almacén: {{ $warehouse->name }}</h1>

@include('admin.warehouses.form', ['warehouse' => $warehouse])
@endsection
