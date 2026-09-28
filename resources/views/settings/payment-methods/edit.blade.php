@extends('layouts.app')

@section('title', 'Editar método de pago - Barbora')

@section('page')
@include('settings.payment-methods.form', ['action' => route('payment-methods.update', $method)])
@endsection
