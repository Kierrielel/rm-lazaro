<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'login_id' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        $throttleKey = Str::lower($credentials['login_id']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return back()->withErrors([
                'login_id' => "Too many login attempts. Please try again in {$seconds} seconds.",
            ])->withInput($request->only('login_id'));
        }

        $identifier = $credentials['login_id'];
        $column = filter_var($identifier, FILTER_VALIDATE_EMAIL) ? 'email'
            : (preg_match('/\A09[0-9]{9}\z/', $identifier) ? 'phone_no' : 'username');
        $user = User::where($column, $identifier)->first();

        if (! $user || $user->archived_at !== null || ! Hash::check($credentials['password'], $user->password)) {
            RateLimiter::hit($throttleKey, 60);

            return back()->withErrors(['login_id' => 'Invalid username/email/phone or password.'])->withInput($request->only('login_id'));
        }

        if (Hash::needsRehash($user->password)) {
            $passwordHash = Hash::make($credentials['password']);
            $updated = User::query()->whereKey($user->id)
                ->where('password', $user->password)
                ->whereNull('archived_at')
                ->update(['password' => $passwordHash]);

            if (! $updated) {
                return back()->withErrors(['login_id' => 'Your account changed. Please log in again.'])
                    ->withInput($request->only('login_id'));
            }

            $user->password = $passwordHash;
        }

        RateLimiter::clear($throttleKey);

        Auth::login($user);
        $request->session()->regenerate();

        return $user->isAdmin()
            ? redirect()->route('admin.dashboard')
            : redirect()->route('home');
    }

    public function showRegister(): View
    {
        return view('auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:50', 'regex:'.User::USERNAME_PATTERN, 'unique:users,username'],
            'email' => ['required', 'email', 'max:100', 'unique:users,email'],
            'phone_no' => ['required', 'regex:/^09[0-9]{9}$/', 'unique:users,phone_no'],
            'password' => ['required', 'string', 'max:255', Password::min(8)->letters()->numbers(), 'confirmed'],
        ], [
            'phone_no.regex' => 'Phone number must start with 09 and be 11 digits long.',
            'username.regex' => 'Start your username with a letter and use only letters, numbers, dots, underscores, or hyphens.',
        ]);

        User::create([
            'username' => $data['username'],
            'email' => $data['email'],
            'phone_no' => $data['phone_no'],
            'password' => Hash::make($data['password']),
            'role' => 'customer',
            'province' => 'No Address',
            'city' => 'No Address',
            'unit' => '',
            'barangay' => '',
        ]);

        return redirect()->route('login')->with('success', 'Account created. You can now log in.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Logged out.');
    }
}
