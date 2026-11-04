@extends('layouts.admin')
@section('title', 'Admin Settings')
@section('content')
<link rel="stylesheet" href="{{ asset('css/settings.css') }}">
<div class="settings-page">
    <div class="settings-header">
        <h2>Admin Settings</h2>
        <p class="subtitle">Manage your account, store information, and inventory alerts.</p>
    </div>
    <nav class="settings-navigation" aria-label="Settings sections">
        <a href="#account-settings">Account</a>
        <a href="#password-settings">Security</a>
        <a href="#store-settings">Store &amp; inventory</a>
    </nav>

    <div class="settings-grid">
        <form id="account-settings" class="settings-card" method="POST" action="{{ route('admin.settings.update') }}">
            @csrf
            @method('PUT')
            <h3 class="card-title">Your account</h3>
            <p class="settings-description">These details belong to your admin login.</p>
            <div class="form-group">
                <label for="name">Full name</label>
                <input id="name" name="name" maxlength="100" autocomplete="name" value="{{ old('name', $user->name) }}">
                @error('name', 'account') <p class="settings-error">{{ $message }}</p> @enderror
            </div>
            <div class="form-group">
                <label for="username">Username</label>
                <input id="username" name="username" maxlength="50" autocomplete="username" value="{{ old('username', $user->username) }}" required>
                @error('username', 'account') <p class="settings-error">{{ $message }}</p> @enderror
            </div>
            <div class="form-group">
                <label for="email">Email address</label>
                <input type="email" id="email" name="email" maxlength="100" autocomplete="email" value="{{ old('email', $user->email) }}" required>
                @error('email', 'account') <p class="settings-error">{{ $message }}</p> @enderror
            </div>
            <div class="form-group">
                <label for="phone_no">Mobile number</label>
                <input type="tel" id="phone_no" name="phone_no" maxlength="11" pattern="09[0-9]{9}" autocomplete="tel" value="{{ old('phone_no', $user->phone_no) }}" required>
                <span class="hint">11 digits starting with 09.</span>
                @error('phone_no', 'account') <p class="settings-error">{{ $message }}</p> @enderror
            </div>
            <div class="form-group">
                <label for="account-current-password">Current password</label>
                <input type="password" id="account-current-password" name="current_password" autocomplete="current-password" required>
                <span class="hint">Confirm your password to save account changes.</span>
                @error('current_password', 'account') <p class="settings-error">{{ $message }}</p> @enderror
            </div>
            <div class="form-buttons"><button class="btn btn-primary" type="submit">Save account details</button></div>
        </form>

        <form id="password-settings" class="settings-card" method="POST" action="{{ route('admin.settings.password') }}">
            @csrf
            @method('PUT')
            <h3 class="card-title">Change password</h3>
            <p class="settings-description">Use at least 8 characters, including letters and numbers.</p>
            <div class="form-group">
                <label for="security-current-password">Current password</label>
                <input type="password" id="security-current-password" name="current_password" autocomplete="current-password" required>
                @error('current_password', 'password') <p class="settings-error">{{ $message }}</p> @enderror
            </div>
            <div class="form-group">
                <label for="password">New password</label>
                <input type="password" id="password" name="password" minlength="8" autocomplete="new-password" required>
                @error('password', 'password') <p class="settings-error">{{ $message }}</p> @enderror
            </div>
            <div class="form-group">
                <label for="password-confirmation">Confirm new password</label>
                <input type="password" id="password-confirmation" name="password_confirmation" minlength="8" autocomplete="new-password" required>
            </div>
            <p class="settings-security-note">Your password is stored securely. You stay signed in on this browser.</p>
            <div class="form-buttons"><button class="btn btn-primary" type="submit">Change password</button></div>
        </form>

        <form id="store-settings" class="settings-card settings-store-card" method="POST" action="{{ route('admin.settings.store') }}">
            @csrf
            @method('PUT')
            <h3 class="card-title">Store information &amp; inventory alerts</h3>
            <p class="settings-description">Store details appear on the customer site. Your store name also appears on finance exports.</p>
            <div class="form-group">
                <label for="store-name">Store name</label>
                <input id="store-name" name="store_name" maxlength="150" value="{{ old('store_name', $storeSettings->store_name) }}" required>
                @error('store_name', 'store') <p class="settings-error">{{ $message }}</p> @enderror
            </div>
            <div class="form-group">
                <label for="store-address">Store address</label>
                <textarea id="store-address" name="store_address" rows="3" maxlength="1000" required>{{ old('store_address', $storeSettings->store_address) }}</textarea>
                @error('store_address', 'store') <p class="settings-error">{{ $message }}</p> @enderror
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="contact-phone">Public contact number</label>
                    <input type="tel" id="contact-phone" name="contact_phone" maxlength="30" value="{{ old('contact_phone', $storeSettings->contact_phone) }}" required>
                    @error('contact_phone', 'store') <p class="settings-error">{{ $message }}</p> @enderror
                </div>
                <div class="form-group">
                    <label for="contact-email">Public email (optional)</label>
                    <input type="email" id="contact-email" name="contact_email" maxlength="100" value="{{ old('contact_email', $storeSettings->contact_email) }}">
                    @error('contact_email', 'store') <p class="settings-error">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="form-group">
                <label for="opening-hours">Opening hours</label>
                <input id="opening-hours" name="opening_hours" maxlength="255" value="{{ old('opening_hours', $storeSettings->opening_hours) }}" required>
                @error('opening_hours', 'store') <p class="settings-error">{{ $message }}</p> @enderror
            </div>
            <div class="form-group">
                <label for="low-stock-threshold">Low-stock alert threshold</label>
                <input type="number" id="low-stock-threshold" name="low_stock_threshold" min="0" max="100000" step="1" value="{{ old('low_stock_threshold', $storeSettings->low_stock_threshold) }}" required>
                <span class="hint">The dashboard flags products with stock at or below this number. Use 0 to show out-of-stock products only.</span>
                @error('low_stock_threshold', 'store') <p class="settings-error">{{ $message }}</p> @enderror
            </div>
            @if ($storeSettings->updated_at)
                <p class="hint">Last updated {{ $storeSettings->updated_at->format('M j, Y g:i A') }} ({{ config('app.timezone') }}).</p>
            @endif
            <div class="form-buttons"><button class="btn btn-primary" type="submit">Save store settings</button></div>
        </form>
    </div>
</div>
@endsection
