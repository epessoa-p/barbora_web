@extends('layouts.app')

@section('title', 'Nueva Empresa - Barbora')

@section('page')
<h1><i class="bi bi-plus-circle"></i> Nueva Empresa</h1>

@include('admin.companies.form', ['company' => null, 'plans' => $plans])
@endsection
