@extends('layouts.app')

@section('title', 'Editar Caja')

@section('page')
@include('admin.cajas.form', [
    'caja'   => $caja,
    'action' => route('cajas.update', $caja),
    'method' => 'PUT',
])
@endsection
