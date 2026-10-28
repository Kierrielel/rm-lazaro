@extends('layouts.admin')

@section('title', 'Review Credit Application')

@section('content')
<h1>Review Credit Application</h1>

<p><strong>Applicant:</strong> {{ collect([$application->first_name, $application->middle_name, $application->last_name])->filter()->join(' ') }}</p>
<p><strong>Username:</strong> {{ $application->user->username }}</p>
<p><strong>Email:</strong> {{ $application->user->email }}</p>
<p><strong>Phone:</strong> {{ $application->phone_number }}</p>
<p><strong>Address:</strong> {{ collect([$application->province, $application->city, $application->barangay, $application->street])->filter()->join(' ') }}</p>
<p>
    <strong>Monthly due date:</strong>
    {{ $application->credit_due_day === '15'
        ? '15th of each month'
        : 'Last day of each month' }}
</p>
<p><strong>Monthly income:</strong>
    {{ $application->monthly_income === null ? 'Not provided' : '₱'.number_format((float) $application->monthly_income, 2) }}
</p>
<p><strong>ID type:</strong> {{ $application->id_type }}</p>
<p><strong>Status:</strong> {{ ucfirst($application->status) }}</p>

<h2>ID photo</h2>
<img
    src="{{ route('admin.credit-applications.document', [$application, 'id']) }}"
    alt="Applicant ID photo"
    style="max-width: 500px;"
>

<h2>Selfie holding ID</h2>
<img
    src="{{ route('admin.credit-applications.document', [$application, 'selfie']) }}"
    alt="Applicant selfie holding ID"
    style="max-width: 500px;"
>

@if ($application->status === 'pending')
    <h2>Approve application</h2>

    <form method="POST" action="{{ route('admin.credit-applications.approve', $application) }}">
        @csrf
        @method('PATCH')

        <label for="credit_limit">Credit limit (&#8369;)</label>
        <input
            id="credit_limit"
            name="credit_limit"
            type="number"
            min="0.01"
            max="99999999.99"
            step="0.01"
            value="5000.00"
            required
        >

        <button type="submit">Approve</button>
    </form>

    <h2>Reject application</h2>

    <form method="POST" action="{{ route('admin.credit-applications.reject', $application) }}">
        @csrf
        @method('PATCH')

        <label for="rejection_reason">Reason (optional)</label>
        <textarea id="rejection_reason" name="rejection_reason" maxlength="2000"></textarea>

        <button type="submit">Reject</button>
    </form>
@else
    <p>This application was reviewed on
        {{ $application->reviewed_at?->format('M j, Y g:i A') ?? 'an unknown date' }}.
    </p>

    @if ($application->rejection_reason)
        <p><strong>Rejection reason:</strong> {{ $application->rejection_reason }}</p>
    @endif
@endif
@endsection