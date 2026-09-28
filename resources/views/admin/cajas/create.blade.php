@extends('layouts.app')

@section('title', 'Nueva Caja')

@section('page')
@include('admin.cajas.form', [
    'caja'   => null,
    'action' => route('cajas.store'),
    'method' => 'POST',
])
@endsection
