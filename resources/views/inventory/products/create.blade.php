@extends('layouts.app')

@section('title', 'Nuevo producto - Barbora')

@section('page')
<h1 class="h4 fw-bold"><i class="bi bi-box"></i> Nuevo producto</h1>

@include('inventory.products.form', ['product' => null, 'categories' => $categories])
@endsection
