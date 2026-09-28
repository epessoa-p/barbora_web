@extends('layouts.app')

@section('title', 'Editar cargo')

@section('page')
@include('admin.cargos.form', ['action' => route('cargos.update', $cargo), 'method' => 'PUT', 'cargo' => $cargo])
@endsection
