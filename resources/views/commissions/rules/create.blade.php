@extends('layouts.app')

@section('title', 'Nueva regla de comisión - Barbora')

@section('page')
<h1 class="h4 fw-bold"><i class="bi bi-percent"></i> Nueva regla de comisión</h1>

@include('commissions.rules.form', ['rule' => null])
@endsection
