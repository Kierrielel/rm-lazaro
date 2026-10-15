<div class="auth-card {{ $mode === 'signup' ? 'is-signup' : '' }}" id="authCard">

    {{-- LOGIN --}}
    <div class="auth-form auth-form--login">
        
    <div class="auth-logo">
        <div class="auth-logo-icon">
            <img src="{{ asset('images/logo.png') }}" alt="RM Lazaro Enterprises logo">
        </div>
        <div class="auth-logo-text">
            <strong>RM Lazaro Enterprises</strong>
            <span>Hardware</span>
        </div>
    </div>

        <h2>Log in</h2>
        <p class="auth-subtitle">Enter your details to continue.</p>

        @if ($errors->any() && $mode === 'login')
            <div class="auth-error">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('login') }}">
            @csrf

            <div class="field">
                <label for="login_id">Username, email, or phone</label>
                <input type="text" name="login_id" id="login_id" value="{{ old('login_id') }}" required>
            </div>

        <div class="field">
                <label for="password">Password</label>
            <div class="password-wrap">
                <input type="password" name="password" id="password" required>
            <button type="button" class="toggle-password" data-target="password">Show</button>
            </div>
        </div>

            <button type="submit">Log In</button>
        </form>

        <div class="auth-divider"><span>or continue with</span></div>

        <div class="auth-socials">
            <a href="#" class="auth-social">
                <svg viewBox="0 0 48 48"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/></svg>
                Google
            </a>
            <a href="#" class="auth-social">
                <svg viewBox="0 0 24 24"><path fill="#1877F2" d="M24 12.07C24 5.41 18.63 0 12 0S0 5.4 0 12.07C0 18.1 4.39 23.1 10.13 24v-8.44H7.08v-3.49h3.04V9.41c0-3.02 1.8-4.7 4.54-4.7 1.31 0 2.68.24 2.68.24v2.97h-1.5c-1.5 0-1.96.93-1.96 1.89v2.26h3.32l-.53 3.5h-2.8V24C19.62 23.1 24 18.1 24 12.07z"/></svg>
                Facebook
            </a>
        </div>

        <p class="auth-switch">
            No account? <a href="{{ route('signup') }}" data-switch="signup">Sign up</a>
        </p>
    </div>

    {{-- SIGN UP--}}
    <div class="auth-form auth-form--signup">
        <div class="auth-logo">
    
            <div class="auth-logo-icon">
        <img src="{{ asset('images/logo.png') }}" alt="RM Lazaro Enterprises logo">
    
    </div>
        <div class="auth-logo-text">
            <strong>RM Lazaro Enterprises</strong>
            <span>Hardware</span>
        </div>
    </div>

        <h2>Create an account</h2>
        <p class="auth-subtitle">It only takes a minute.</p>

        @if ($errors->any() && $mode === 'signup')
            <div class="auth-error">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('signup') }}">
            @csrf

            <div class="field">
                <label for="username">Username</label>
                <input type="text" name="username" id="username" value="{{ old('username') }}" required>
            </div>

            <div class="field">
                <label for="email">Email</label>
                <input type="email" name="email" id="email" value="{{ old('email') }}" required>
            </div>

            <div class="field">
                <label for="phone_no">Phone number</label>
                <input type="text" name="phone_no" id="phone_no" value="{{ old('phone_no') }}" required>
            </div>

        <div class="field">
                <label for="signup_password">Password (minimum 6 characters)</label>
            <div class="password-wrap">
                <input type="password" name="password" id="signup_password" required>
                <button type="button" class="toggle-password" data-target="signup_password">Show</button>
            </div>
        </div>

            <button type="submit">Sign Up</button>
        </form>

        <div class="auth-divider"><span>or sign up with</span></div>

        <div class="auth-socials">
            <a href="#" class="auth-social">
                <svg viewBox="0 0 48 48"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/></svg>
                Google
            </a>
            <a href="#" class="auth-social">
                <svg viewBox="0 0 24 24"><path fill="#1877F2" d="M24 12.07C24 5.41 18.63 0 12 0S0 5.4 0 12.07C0 18.1 4.39 23.1 10.13 24v-8.44H7.08v-3.49h3.04V9.41c0-3.02 1.8-4.7 4.54-4.7 1.31 0 2.68.24 2.68.24v2.97h-1.5c-1.5 0-1.96.93-1.96 1.89v2.26h3.32l-.53 3.5h-2.8V24C19.62 23.1 24 18.1 24 12.07z"/></svg>
                Facebook
            </a>
        </div>

        <p class="auth-switch">
            Already have an account? <a href="{{ route('login') }}" data-switch="login">Log in</a>
        </p>
    </div>

    {{-- For sliding imgae --}}
    <div class="auth-panel">
        <div class="auth-panel-text auth-panel-text--login">
            <h2>Welcome back!</h2>
            <p>Log in to browse products and track your orders.</p>
            <a href="{{ route('signup') }}" class="auth-ghost-btn" data-switch="signup">Sign up</a>
        </div>

        <div class="auth-panel-text auth-panel-text--signup">
            <h2>Join us!</h2>
            <p>Create an account to start shopping with us.</p>
            <a href="{{ route('login') }}" class="auth-ghost-btn" data-switch="login">Log in</a>
        </div>
    </div>

</div>

{{-- For switch modal card and password show --}}
<script>
    const card = document.getElementById('authCard');

    document.querySelectorAll('[data-switch]').forEach(function (link) {
        link.addEventListener('click', function (e) {
            e.preventDefault();
            card.classList.toggle('is-signup', link.dataset.switch === 'signup');
            history.replaceState(null, '', link.href);
        });
    });

    document.querySelectorAll('.toggle-password').forEach(function (btn) {
    btn.addEventListener('click', function () {
        const input = document.getElementById(btn.dataset.target);
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        btn.textContent = show ? 'Hide' : 'Show';
    });
});
</script>