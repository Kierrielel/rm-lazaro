@extends('layouts.admin')

@section('title', 'Settings')

@section('content')
<h2>Account Settings</h2>

<form method="POST" action="{{ route('admin.settings.update') }}">
    @csrf
    @method('PUT')

    <label for="username">Username</label>
    <input type="text" name="username" id="username" value="{{ old('username', $user->username) }}" required>

    <label for="email">Email</label>
    <input type="email" name="email" id="email" value="{{ old('email', $user->email) }}" required>

    <label for="phone_no">Phone number</label>
    <input type="text" name="phone_no" id="phone_no" value="{{ old('phone_no', $user->phone_no) }}" required>

    <label for="password">New password (leave blank to keep current)</label>
    <input type="password" name="password" id="password">

    <button type="submit">Save Changes</button>
</form>
@endsection
