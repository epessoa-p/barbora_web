@extends('layouts.app')

@section('title', 'Editar cliente - Barbora')

@section('page')
<h1 class="h4 fw-bold"><i class="bi bi-pencil"></i> Editar cliente: {{ $client->full_name }}</h1>

@include('clients.form', ['client' => $client])
@endsection
