<?php

namespace App;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AccountPassword
{
    public function change(Request $request): void
    {
        $data = $request->validateWithBag('password', [
            'current_password' => ['required', 'current_password:web'],
            'password' => [
                'required', 'string', 'max:72', Password::min(8)->letters()->numbers(), 'confirmed', 'different:current_password',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (is_string($value) && strlen($value) > 72) {
                        $fail('Please use a shorter password.');
                    }
                },
            ],
        ]);

        DB::transaction(function () use ($request, $data): void {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            if (! Hash::check($data['current_password'], $user->password)) {
                throw ValidationException::withMessages(['current_password' => 'The password is incorrect.'])->errorBag('password');
            }
            $user->password = Hash::make($data['password']);
            $user->remember_token = Str::random(60);
            $user->save();

            if (config('session.driver') === 'database') {
                DB::connection(config('session.connection'))->table(config('session.table', 'sessions'))
                    ->where('user_id', $user->id)
                    ->where('id', '!=', $request->session()->getId())
                    ->delete();
            }
        }, 3);

        $request->user()->refresh();
        $request->session()->regenerate(true);
    }
}
