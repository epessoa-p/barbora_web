@extends('layouts.app')

@section('title', 'Nuevo Plan - Barbora')

@section('page')
<h1><i class="bi bi-plus-circle"></i> Nuevo Plan</h1>

@include('admin.plans.form', ['plan' => null])
@endsection
