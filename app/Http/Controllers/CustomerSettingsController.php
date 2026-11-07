<?php

namespace App\Http\Controllers;

use App\AccountPassword;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\Rule;

class CustomerSettingsController extends Controller
{
    public function edit(Request $request): View
    {
        return view('customer.settings', [
            'user' => $request->user(),
            'cities' => $this->cities(),
        ]);
    }

    public function updateAccount(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validateWithBag('account', [
            'name' => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', 'max:50', 'regex:'.User::USERNAME_PATTERN, Rule::unique('users', 'username')->ignore($user)],
            'email' => ['required', 'email', 'max:100', Rule::unique('users', 'email')->ignore($user)],
            'phone_no' => ['required', 'string', 'regex:/^09[0-9]{9}$/', Rule::unique('users', 'phone_no')->ignore($user)],
            'current_password' => ['required', 'current_password:web'],
        ], [
            'username.regex' => 'Start with a letter and use only letters, numbers, dots, underscores, or hyphens.',
            'phone_no.regex' => 'Enter an 11-digit mobile number starting with 09.',
        ]);
        unset($data['current_password']);
        $user->fill($data)->save();
        $request->session()->regenerate();

        return redirect()->route('customer.settings')->with('success', 'Your profile details have been updated.');
    }

    public function updateAddress(Request $request): RedirectResponse
    {
        $cities = $this->cities();
        $city = collect($cities)->firstWhere('name', $request->input('city'));
        $data = $request->validateWithBag('address', [
            'province' => ['required', 'string', Rule::in(['Bulacan'])],
            'city' => ['required', 'string', Rule::in(array_column($cities, 'name'))],
            'barangay' => ['required', 'string', Rule::in($city['barangays'] ?? [])],
            'unit' => ['required', 'string', 'max:255'],
        ], [
            'city.in' => 'Choose a city or municipality from the Bulacan address list.',
            'barangay.in' => 'Choose a barangay that belongs to the selected city or municipality.',
            'unit.required' => 'Enter your house number, street, or subdivision.',
        ]);
        $request->user()->fill($data)->save();

        return redirect()->to(route('customer.settings').'#billing-address')->with('success', 'Your billing address has been updated.');
    }

    public function updatePassword(Request $request, AccountPassword $password): RedirectResponse
    {
        $password->change($request);

        return redirect()->to(route('customer.settings').'#change-password')->with('success', 'Your password has been changed.');
    }

    /** @return list<array{name: string, barangays: list<string>}> */
    private function cities(): array
    {
        return File::json(public_path('data/bulacan-address.json'))['cities'];
    }
}
