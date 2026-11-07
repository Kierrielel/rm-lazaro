@extends('layouts.customer')

@section('title', 'Account Settings')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/account-overview.css') }}">
    <link rel="stylesheet" href="{{ asset('css/customer-settings.css') }}">
@endpush

@section('content')
<div class="account-overview account-layout">
    @include('customer.account-sidebar')

    <div class="account-main customer-settings">
        <h1>Account Settings</h1>
        <p class="account-settings-intro">Manage your profile, billing address, and password.</p>

        <section class="account-card" id="profile-details" aria-labelledby="profile-title">
            <h2 id="profile-title">Profile details</h2>
            <p class="account-settings-help">Use your current password to confirm changes to your account details.</p>
            <form method="POST" action="{{ route('customer.settings.update') }}">
                @csrf
                @method('PUT')
                <div class="account-settings-grid">
                    <div class="account-settings-field">
                        <label for="profile-name">Full name</label>
                        <input id="profile-name" name="name" value="{{ old('name', $user->name) }}" maxlength="100" autocomplete="name" required>
                        @error('name', 'account') <p class="account-settings-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="account-settings-field">
                        <label for="profile-username">Username</label>
                        <input id="profile-username" name="username" value="{{ old('username', $user->username) }}" maxlength="50" autocomplete="username" required>
                        <small>Start with a letter. Letters, numbers, dots, underscores, and hyphens are allowed.</small>
                        @error('username', 'account') <p class="account-settings-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="account-settings-field">
                        <label for="profile-email">Email address</label>
                        <input type="email" id="profile-email" name="email" value="{{ old('email', $user->email) }}" maxlength="100" autocomplete="email" required>
                        @error('email', 'account') <p class="account-settings-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="account-settings-field">
                        <label for="profile-phone">Mobile number</label>
                        <input type="tel" id="profile-phone" name="phone_no" value="{{ old('phone_no', $user->phone_no) }}" maxlength="11" pattern="09[0-9]{9}" placeholder="09XXXXXXXXX" autocomplete="tel" required>
                        @error('phone_no', 'account') <p class="account-settings-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="account-settings-field">
                        <label for="profile-current-password">Current password</label>
                        <input type="password" id="profile-current-password" name="current_password" autocomplete="current-password" required>
                        @error('current_password', 'account') <p class="account-settings-error">{{ $message }}</p> @enderror
                    </div>
                </div>
                <button type="submit" class="account-settings-save">Save profile</button>
            </form>
        </section>

        <section class="account-card" id="billing-address" aria-labelledby="billing-title">
            <h2 id="billing-title">Billing address</h2>
            <p class="account-settings-help">This address is also used as your delivery address. Choose a location in Bulacan.</p>
            <div class="account-settings-address-preview">
                <strong>{{ $user->name ?: $user->username }}</strong>
                <p>{{ $user->hasAddress() ? $user->full_address : 'You have not saved a complete address yet.' }}</p>
            </div>
            @php
                $selectedCity = old('city', $user->city);
                $selectedBarangay = old('barangay', $user->barangay);
                $barangays = collect($cities)->firstWhere('name', $selectedCity)['barangays'] ?? [];
            @endphp
            <form method="POST" action="{{ route('customer.settings.address') }}">
                @csrf
                @method('PUT')
                <div class="account-settings-grid">
                    <div class="account-settings-field">
                        <label for="billing-province">Province</label>
                        <input id="billing-province" name="province" value="Bulacan" autocomplete="address-level1" readonly required>
                        @error('province', 'address') <p class="account-settings-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="account-settings-field">
                        <label for="billing-city">City / Municipality</label>
                        <select id="billing-city" name="city" autocomplete="address-level2" required>
                            <option value="">Choose a city or municipality</option>
                            @foreach ($cities as $city)
                                <option value="{{ $city['name'] }}" data-barangays="{{ json_encode($city['barangays']) }}" @selected($selectedCity === $city['name'])>{{ $city['name'] }}</option>
                            @endforeach
                        </select>
                        @error('city', 'address') <p class="account-settings-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="account-settings-field">
                        <label for="billing-barangay">Barangay</label>
                        <select id="billing-barangay" name="barangay" autocomplete="address-level3" required @disabled(empty($barangays))>
                            <option value="">Choose a barangay</option>
                            @foreach ($barangays as $barangay)
                                <option value="{{ $barangay }}" @selected($selectedBarangay === $barangay)>{{ $barangay }}</option>
                            @endforeach
                        </select>
                        @error('barangay', 'address') <p class="account-settings-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="account-settings-field">
                        <label for="billing-unit">House number / Street / Subdivision</label>
                        <input id="billing-unit" name="unit" value="{{ old('unit', $user->unit) }}" maxlength="255" autocomplete="street-address" required>
                        @error('unit', 'address') <p class="account-settings-error">{{ $message }}</p> @enderror
                    </div>
                </div>
                <button type="submit" class="account-settings-save">Save billing address</button>
            </form>
        </section>

        <section class="account-card" id="change-password" aria-labelledby="password-title">
            <h2 id="password-title">Change password</h2>
            <p class="account-settings-help">Choose a password with at least 8 characters, including letters and numbers.</p>
            <form method="POST" action="{{ route('customer.settings.password') }}">
                @csrf
                @method('PUT')
                <div class="account-settings-grid">
                    <div class="account-settings-field account-settings-full-width">
                        <label for="security-current-password">Current password</label>
                        <input type="password" id="security-current-password" name="current_password" autocomplete="current-password" required>
                        @error('current_password', 'password') <p class="account-settings-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="account-settings-field">
                        <label for="security-new-password">New password</label>
                        <input type="password" id="security-new-password" name="password" minlength="8" maxlength="72" autocomplete="new-password" required>
                        @error('password', 'password') <p class="account-settings-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="account-settings-field">
                        <label for="security-confirm-password">Confirm new password</label>
                        <input type="password" id="security-confirm-password" name="password_confirmation" minlength="8" maxlength="72" autocomplete="new-password" required>
                    </div>
                </div>
                <button type="submit" class="account-settings-save">Change password</button>
            </form>
        </section>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const citySelect = document.getElementById('billing-city');
    const barangaySelect = document.getElementById('billing-barangay');

    citySelect.addEventListener('change', function () {
        const selectedOption = citySelect.options[citySelect.selectedIndex];
        const barangays = JSON.parse(selectedOption.dataset.barangays || '[]');
        barangaySelect.replaceChildren(new Option('Choose a barangay', ''));
        barangays.forEach(name => barangaySelect.appendChild(new Option(name, name)));
        barangaySelect.disabled = barangays.length === 0;
    });
});
</script>
@endsection
