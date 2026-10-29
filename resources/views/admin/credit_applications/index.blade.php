@extends('layouts.admin')

@section('title', 'Credit Applications')

@section('content')
<link rel="stylesheet" href="{{ asset('css/credit_applications.css') }}">

<div class="orders-page">
    <div class="orders-header">
        <div>
            <h2>Credit Applications</h2>
            <p class="subtitle">Review and manage customer credit applications.</p>
        </div>

        <form method="GET" action="{{ route('admin.credit-applications.index') }}" class="filter-form">
            <select name="status" aria-label="Filter by application status" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                <option value="pending" @selected($status === 'pending')>Pending</option>
                <option value="approved" @selected($status === 'approved')>Approved</option>
                <option value="rejected" @selected($status === 'rejected')>Rejected</option>
            </select>
        </form>
    </div>

    <div class="table-card">
        <table class="order-table credit-applications-table">
            <thead>
                <tr>
                    <th>Applicant</th>
                    <th>Username</th>
                    <th>Status</th>
                    <th>Submitted</th>
                    <th class="actions-col">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($applications as $application)
                    @php
                        $applicantName = collect([$application->first_name, $application->middle_name, $application->last_name])->filter()->join(' ');
                    @endphp
                    <tr>
                        <td data-label="Applicant" class="credit-applicant-name">{{ $applicantName ?: 'Name not provided' }}</td>
                        <td data-label="Username">{{ $application->user->username }}</td>
                        <td data-label="Status">
                            <span class="credit-application-status credit-status-{{ strtolower($application->status) }}">
                                {{ ucfirst($application->status) }}
                            </span>
                        </td>
                        <td data-label="Submitted" class="order-date">{{ $application->created_at->format('M d, Y') }}</td>
                        <td data-label="Action" class="actions-col">
                            <a href="{{ route('admin.credit-applications.show', $application) }}" class="btn btn-blue">Review</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="empty-row">No applications found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($applications->hasPages())
        <div class="pagination-wrap">
            {{ $applications->links('vendor.pagination.admin-compact') }}
        </div>
    @endif
</div>
@endsection
