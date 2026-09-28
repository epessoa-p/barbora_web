@extends('layouts.app')

@section('title', 'Nuevo cargo')

@section('page')
@include('admin.cargos.form', ['action' => route('cargos.store'), 'method' => 'POST', 'cargo' => null])
@endsection
