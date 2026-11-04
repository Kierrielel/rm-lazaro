<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StoreSetting;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class SettingsController extends Controller
{
    public function edit(Request $request): View
    {
        return view('admin.settings', ['user' => $request->user(), 'storeSettings' => StoreSetting::current()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validateWithBag('account', [
            'name' => ['nullable', 'string', 'max:100'],
            'username' => ['required', 'string', 'max:50', 'regex:'.User::USERNAME_PATTERN, Rule::unique('users', 'username')->ignore($user)],
            'email' => ['required', 'email', 'max:100', Rule::unique('users', 'email')->ignore($user)],
            'phone_no' => ['required', 'regex:/^09[0-9]{9}$/', Rule::unique('users', 'phone_no')->ignore($user)],
            'current_password' => ['required', 'current_password:web'],
        ]);
        unset($data['current_password']);
        $user->fill($data)->save();
        $request->session()->regenerate();

        return redirect()->route('admin.settings')->with('success', 'Account details updated.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag('password', [
            'current_password' => ['required', 'current_password:web'],
            'password' => ['required', Password::min(8)->letters()->numbers(), 'confirmed', 'different:current_password'],
        ]);
        DB::transaction(function () use ($request, $data) {
            $user = $request->user();
            $user->password = Hash::make($data['password']);
            $user->remember_token = Str::random(60);
            $user->save();
            if (config('session.driver') === 'database') {
                DB::connection(config('session.connection'))->table(config('session.table', 'sessions'))
                    ->where('user_id', $user->id)->where('id', '!=', $request->session()->getId())->delete();
            }
        });
        $request->session()->regenerate(true);

        return redirect()->route('admin.settings')->with('success', 'Password changed successfully.');
    }

    public function updateStore(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag('store', [
            'store_name' => ['required', 'string', 'max:150'],
            'store_address' => ['required', 'string', 'max:1000'],
            'contact_phone' => ['required', 'string', 'max:30', 'regex:/^\+?[0-9][0-9\s()\-]{6,28}$/'],
            'contact_email' => ['nullable', 'email', 'max:100'],
            'opening_hours' => ['required', 'string', 'max:255'],
            'low_stock_threshold' => ['required', 'integer', 'min:0', 'max:100000'],
        ]);
        DB::transaction(function () use ($request, $data) {
            $settings = StoreSetting::query()->lockForUpdate()->findOrFail(1);
            $settings->fill($data);
            $settings->updated_by = $request->user()->id;
            $settings->save();
        });

        return redirect()->route('admin.settings')->with('success', 'Store settings saved.');
    }
}
