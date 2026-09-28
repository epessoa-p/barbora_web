@extends('layouts.app')

@section('title', 'Nueva categoría - Barbora')

@section('page')
<h1 class="h4 fw-bold"><i class="bi bi-tags"></i> Nueva categoría de servicio</h1>

@include('services.categories.form', ['category' => null])
@endsection
