@extends('layouts.app')

@section('title', 'Editar Plan - Barbora')

@section('page')
<h1><i class="bi bi-pencil"></i> Editar Plan: {{ $plan->name }}</h1>

@include('admin.plans.form', ['plan' => $plan])
@endsection
