@extends('layouts.admin')

@section('title', 'Settings')

@section('content')
<link rel="stylesheet" href="{{ asset('css/inventory.css') }}">
<link rel="stylesheet" href="{{ asset('css/settings.css') }}">

<div class="settings-page">

    <div class="settings-header">
        <h2>Account Settings</h2>
        <p class="subtitle">Update your personal details and password.</p>
    </div>

    <form method="POST" action="{{ route('admin.settings.update') }}">
        @csrf
        @method('PUT')

        <div class="settings-card">
            <div class="card-title">Account</div>

            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" name="username" id="username" value="{{ old('username', $user->username) }}" required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" name="email" id="email" value="{{ old('email', $user->email) }}" required>
                </div>

                <div class="form-group">
                    <label for="phone_no">Phone number</label>
                    <input type="text" name="phone_no" id="phone_no" value="{{ old('phone_no', $user->phone_no) }}" required>
                </div>
            </div>
        </div>

        <div class="settings-card">
            <div class="card-title">Change Password</div>

            <div class="form-group">
                <label for="password">New password</label>
                <input type="password" name="password" id="password" placeholder="Leave blank to keep current">
                <span class="hint">Leave this empty if you don't want to change your password.</span>
            </div>
        </div>

        <div class="form-buttons">
            <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>
    </form>

</div>
@endsection