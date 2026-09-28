@extends('layouts.app')

@section('title', 'Editar bloqueo de agenda - Barbora')

@section('page')
@include('appointments.blocks.form', [
    'action' => route('agenda-blocks.update', $block),
    'method' => 'PUT',
    'defaults' => [],
])
@endsection
