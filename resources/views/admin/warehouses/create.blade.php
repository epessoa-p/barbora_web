@extends('layouts.app')

@section('title', 'Nuevo Almacén - Barbora')

@section('page')
<h1 class="h4 fw-bold"><i class="bi bi-box-seam"></i> Nuevo Almacén</h1>

@include('admin.warehouses.form', ['warehouse' => null])
@endsection
