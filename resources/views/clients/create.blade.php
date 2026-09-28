@extends('layouts.app')

@section('title', 'Nuevo cliente - Barbora')

@section('page')
<h1 class="h4 fw-bold"><i class="bi bi-person-plus"></i> Nuevo cliente</h1>

@include('clients.form', ['client' => null])
@endsection
