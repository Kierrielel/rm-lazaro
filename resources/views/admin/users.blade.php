@extends('layouts.admin')

@section('title', 'Users')

@section('content')
<link rel="stylesheet" href="{{ asset('css/inventory.css') }}">
<link rel="stylesheet" href="{{ asset('css/users.css') }}">

<div class="users-page">

    <div class="users-header">
        <h2>Users</h2>
        <p class="subtitle">Manage active and archived accounts. Archiving preserves order and payment history.</p>
    </div>

    <form method="GET" action="{{ route('admin.users') }}" class="search-form">
        <label for="account-status">Accounts</label>
        <select id="account-status" name="status">
            <option value="active" @selected($accountStatus === 'active')>Active</option>
            <option value="archived" @selected($accountStatus === 'archived')>Archived</option>
            <option value="all" @selected($accountStatus === 'all')>All accounts</option>
        </select>
        <input type="search" name="search" placeholder="Search by username or email" value="{{ request('search') }}">
        <button type="submit" class="btn btn-dark">Search</button>
    </form>

    <div class="table-card">
        <table class="users-table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Phone</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Joined</th>
                    <th class="actions-col">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $user)
                    <tr>
                        <td data-label="User">
                            <div class="user-info">
                                <span class="user-letter">{{ strtoupper(mb_substr($user->username, 0, 1)) }}</span>
                                <div>
                                    <div class="user-name">{{ $user->username }}</div>
                                    <div class="user-email">{{ $user->email }}</div>
                                </div>
                            </div>
                        </td>

                        <td data-label="Phone">{{ $user->phone_no ?: '—' }}</td>

                        <td data-label="Role">
                            <span class="role-badge role-{{ strtolower($user->role) }}">{{ ucfirst($user->role) }}</span>
                        </td>

                        <td data-label="Status">
                            <span class="role-badge account-status-{{ $user->archived_at ? 'archived' : 'active' }}">
                                {{ $user->archived_at ? 'Archived' : 'Active' }}
                            </span>
                            @if ($user->archived_at)
                                <div class="user-date">{{ $user->archived_at->format('M d, Y') }}</div>
                            @endif
                        </td>

                        <td data-label="Joined" class="user-date">{{ $user->created_at?->format('M d, Y') }}</td>

                        <td data-label="Actions" class="actions-col">
                            @if ($user->role !== 'admin')
                                @if ($user->archived_at)
                                    <form action="{{ route('admin.users.restore', $user) }}" method="POST"
                                          onsubmit="return confirm('Restore this account and allow the user to sign in again?');">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn btn-dark">Restore</button>
                                    </form>
                                @else
                                    <form action="{{ route('admin.users.archive', $user) }}" method="POST"
                                          onsubmit="return confirm('Archive this account? The user will be signed out and blocked from signing in. Their records will be preserved.');">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn btn-danger">Archive</button>
                                    </form>
                                @endif
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="empty-row">No users found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="pagination-wrap">
        {{ $users->links('vendor.pagination.admin-compact') }}
    </div>

</div>
@endsection
