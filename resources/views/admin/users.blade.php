@extends('layouts.admin')

@section('title', 'Users')

@section('content')
<link rel="stylesheet" href="{{ asset('css/inventory.css') }}">
<link rel="stylesheet" href="{{ asset('css/users.css') }}">

<div class="users-page">

    <div class="users-header">
        <h2>Users</h2>
        <p class="subtitle">View and manage registered accounts.</p>
    </div>

    <form method="GET" action="{{ route('admin.users') }}" class="search-form">
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

                        <td data-label="Joined" class="user-date">{{ $user->created_at?->format('M d, Y') }}</td>

                        <td data-label="Actions" class="actions-col">
                            @if ($user->role !== 'admin')
                                <form action="{{ route('admin.users.destroy', $user) }}" method="POST"
                                      onsubmit="return confirm('Delete this user?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger">Delete</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="empty-row">No users found.</td>
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
