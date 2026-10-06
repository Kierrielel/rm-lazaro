<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class SettingsController extends Controller
{
    public function edit(Request $request)
    {
        return view('admin.settings', ['user' => $request->user()]);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'username' => ['required', 'string', 'max:50', 'unique:users,username,' . $user->id],
            'email' => ['required', 'email', 'max:100', 'unique:users,email,' . $user->id],
            'phone_no' => ['required', 'regex:/^09[0-9]{9}$/', 'unique:users,phone_no,' . $user->id],
            'password' => ['nullable', 'string', 'min:6'],
        ]);

        if (!empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->username = $data['username'];
        $user->email = $data['email'];
        $user->phone_no = $data['phone_no'];
        $user->save();

        return back()->with('success', 'Settings updated.');
    }
}
