<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'login_id' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = Str::lower($credentials['login_id']) . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return back()->withErrors([
                'login_id' => "Too many login attempts. Please try again in {$seconds} seconds.",
            ])->withInput();
        }

        $user = User::where('username', $credentials['login_id'])
            ->orWhere('email', $credentials['login_id'])
            ->orWhere('phone_no', $credentials['login_id'])
            ->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            RateLimiter::hit($throttleKey, 60);

            return back()->withErrors(['login_id' => 'Invalid username/email/phone or password.'])->withInput();
        }

        RateLimiter::clear($throttleKey);

        Auth::login($user);
        $request->session()->regenerate();

        return $user->isAdmin()
            ? redirect()->route('admin.dashboard')
            : redirect()->route('home');
    }

    public function showRegister()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:50', 'unique:users,username'],
            'email' => ['required', 'email', 'max:100', 'unique:users,email'],
            'phone_no' => ['required', 'regex:/^09[0-9]{9}$/', 'unique:users,phone_no'],
            'password' => ['required', 'string', 'min:6'],
        ], [
            'phone_no.regex' => 'Phone number must start with 09 and be 11 digits long.',
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

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Logged out.');
    }
}
