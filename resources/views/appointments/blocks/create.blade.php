@extends('layouts.app')

@section('title', 'Nuevo bloqueo de agenda - Barbora')

@section('page')
@include('appointments.blocks.form', [
    'action' => route('agenda-blocks.store'),
    'method' => 'POST',
])
@endsection
