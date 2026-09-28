@extends('layouts.app')

@section('title', 'Editar categoría - Barbora')

@section('page')
<h1 class="h4 fw-bold"><i class="bi bi-pencil"></i> Editar categoría: {{ $category->name }}</h1>

@include('inventory.categories.form', ['category' => $category])
@endsection
