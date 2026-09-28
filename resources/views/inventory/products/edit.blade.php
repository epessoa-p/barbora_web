@extends('layouts.app')

@section('title', 'Editar producto - Barbora')

@section('page')
<h1 class="h4 fw-bold"><i class="bi bi-pencil"></i> Editar producto: {{ $product->name }}</h1>

@include('inventory.products.form', ['product' => $product, 'categories' => $categories])
@endsection
