@extends('layouts.app')

@section('title', 'Editar servicio - Barbora')

@section('page')
<h1 class="h4 fw-bold"><i class="bi bi-pencil"></i> Editar servicio: {{ $service->name }}</h1>

@include('services.form', ['service' => $service, 'categories' => $categories])
@endsection
