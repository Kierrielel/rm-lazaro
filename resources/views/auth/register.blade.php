@extends('layouts.customer')

@section('title', 'Sign Up')

@section('hide_layout', true)

@push('body-class')
    login-page
@endpush

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/login.css') }}">
@endpush

@section('content')
    @include('auth.card', ['mode' => 'signup'])
@endsection