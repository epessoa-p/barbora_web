@extends('layouts.app')

@section('title', 'Editar Empresa - Barbora')

@section('page')
<h1><i class="bi bi-pencil"></i> Editar Empresa</h1>

@include('admin.companies.form', ['company' => $company])
@endsection
