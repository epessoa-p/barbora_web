@extends('layouts.app')

@section('title', 'Editar regla - Barbora')

@section('page')
<h1 class="h4 fw-bold"><i class="bi bi-pencil"></i> Editar regla de comisión</h1>
<p class="text-muted">Lo ya devengado no cambia: cada comisión guardó su tipo y su valor.</p>

@include('commissions.rules.form', ['rule' => $rule])
@endsection
