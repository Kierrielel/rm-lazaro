@extends('layouts.customer')

@section('title', 'Sign Up')

@section('content')
<div class="login-container">
    <h2>Create an account</h2>
    <form method="POST" action="{{ route('signup') }}">
        @csrf
        <label for="username">Username</label>
        <input type="text" name="username" id="username" value="{{ old('username') }}" required>

        <label for="email">Email</label>
        <input type="email" name="email" id="email" value="{{ old('email') }}" required>

        <label for="phone_no">Phone number (09XXXXXXXXX)</label>
        <input type="text" name="phone_no" id="phone_no" value="{{ old('phone_no') }}" required>

        <label for="password">Password (min. 6 characters)</label>
        <input type="password" name="password" id="password" required>

        <button type="submit">Sign Up</button>
    </form>
    <p>Already have an account? <a href="{{ route('login') }}">Log in</a></p>
</div>
@endsection
