<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:active,archived,all'],
        ]);
        $accountStatus = $filters['status'] ?? 'active';
        $query = User::query();

        if ($accountStatus === 'active') {
            $query->whereNull('archived_at');
        } elseif ($accountStatus === 'archived') {
            $query->whereNotNull('archived_at');
        }

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->orderBy('username')->paginate(15)->withQueryString();

        return view('admin.users', compact('users', 'accountStatus'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_if($user->archived_at !== null, 403, 'Restore this account before editing it.');
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:100'],
            'role' => ['required', 'in:admin,customer'],
            'province' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:255'],
            'barangay' => ['nullable', 'string', 'max:255'],
        ]);

        $user->update($data);

        return back()->with('success', 'User updated.');
    }

    public function archive(User $user): RedirectResponse
    {
        DB::transaction(function () use ($user) {
            $account = User::query()->lockForUpdate()->findOrFail($user->id);
            abort_if($account->role === 'admin', 403, 'Cannot archive an admin account.');
            $account->archived_at ??= now();
            $account->remember_token = Str::random(60);
            $account->save();

            if (config('session.driver') === 'database') {
                DB::connection(config('session.connection'))->table(config('session.table', 'sessions'))
                    ->where('user_id', $account->id)->delete();
            }
        });

        return redirect()->route('admin.users')->with('success', 'User archived. Their order and payment history has been preserved.');
    }

    public function restore(User $user): RedirectResponse
    {
        $user->archived_at = null;
        $user->remember_token = Str::random(60);
        $user->save();

        return redirect()->route('admin.users', ['status' => 'archived'])->with('success', 'User restored. They can sign in again.');
    }
}
