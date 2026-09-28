@extends('layouts.app')

@section('title', 'Nuevo servicio - Barbora')

@section('page')
<h1 class="h4 fw-bold"><i class="bi bi-scissors"></i> Nuevo servicio</h1>

@include('services.form', ['service' => null, 'categories' => $categories])
@endsection
