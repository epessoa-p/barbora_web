@extends('layouts.app')

@section('title', 'Nueva categoría - Barbora')

@section('page')
<h1 class="h4 fw-bold"><i class="bi bi-tags"></i> Nueva categoría de producto</h1>

@include('inventory.categories.form', ['category' => null])
@endsection
