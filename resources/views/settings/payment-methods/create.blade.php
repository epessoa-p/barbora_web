@extends('layouts.app')

@section('title', 'Nuevo método de pago - Barbora')

@section('page')
@include('settings.payment-methods.form', ['action' => route('payment-methods.store')])
@endsection
