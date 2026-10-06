@extends('layouts.admin')

@section('title', 'Users')

@section('content')
<h2>Users</h2>

<form method="GET" action="{{ route('admin.users') }}" class="search-form">
    <input type="search" name="search" placeholder="Search by username or email" value="{{ request('search') }}">
    <button type="submit">Search</button>
</form>

<table>
    <thead>
        <tr>
            <th>Username</th>
            <th>Email</th>
            <th>Phone</th>
            <th>Role</th>
            <th>Joined</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($users as $user)
            <tr>
                <td>{{ $user->username }}</td>
                <td>{{ $user->email }}</td>
                <td>{{ $user->phone_no }}</td>
                <td>{{ ucfirst($user->role) }}</td>
                <td>{{ $user->created_at?->format('M d, Y') }}</td>
                <td>
                    @if ($user->role !== 'admin')
                        <form action="{{ route('admin.users.destroy', $user) }}" method="POST"
                              onsubmit="return confirm('Delete this user?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit">Delete</button>
                        </form>
                    @endif
                </td>
            </tr>
        @endforeach
    </tbody>
</table>

{{ $users->links() }}
@endsection
