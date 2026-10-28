@extends('layouts.admin')

@section('title', 'Credit Applications')

@section('content')
<h1>Credit Applications</h1>

<form method="GET" action="{{ route('admin.credit-applications.index') }}">
    <label for="status">Filter by status</label>
    <select id="status" name="status">
        <option value="">All</option>
        <option value="pending" @selected($status === 'pending')>Pending</option>
        <option value="approved" @selected($status === 'approved')>Approved</option>
        <option value="rejected" @selected($status === 'rejected')>Rejected</option>
    </select>
    <button type="submit">Filter</button>
</form>

<table>
    <thead>
        <tr>
            <th>Applicant</th>
            <th>Username</th>
            <th>Status</th>
            <th>Submitted</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        @forelse ($applications as $application)
            <tr>
                <td>{{ collect([$application->first_name, $application->middle_name, $application->last_name])->filter()->join(' ') }}</td>
                <td>{{ $application->user->username }}</td>
                <td>{{ ucfirst($application->status) }}</td>
                <td>{{ $application->created_at->format('M j, Y') }}</td>
                <td>
                    <a href="{{ route('admin.credit-applications.show', $application) }}">
                        Review
                    </a>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="5">No applications found.</td>
            </tr>
        @endforelse
    </tbody>
</table>

{{ $applications->links() }}
@endsection