@extends('layouts.customer')

@section('title', 'Login')

@section('content')
<div class="login-container">
    <h2>Login</h2>
    <form method="POST" action="{{ route('login') }}">
        @csrf
        <label for="login_id">Username, email, or phone</label>
        <input type="text" name="login_id" id="login_id" value="{{ old('login_id') }}" required>

        <label for="password">Password</label>
        <input type="password" name="password" id="password" required>

        <button type="submit">Log In</button>
    </form>
    <p>No account? <a href="{{ route('signup') }}">Sign up</a></p>
</div>
@endsection
