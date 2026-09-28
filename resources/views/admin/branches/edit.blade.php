@extends('layouts.app')

@section('title', 'Editar sucursal')

@section('page')
@include('admin.branches.form', ['action' => route('branches.update', $branch), 'method' => 'PUT', 'branch' => $branch])
@endsection