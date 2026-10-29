@extends('layouts.admin')

@section('title', 'Review Credit Application')

@section('content')
<link rel="stylesheet" href="{{ asset('css/show_application.css') }}">

@php
    $idTypes = [
        'national_id'     => 'National ID',
        'drivers_license' => "Driver's License",
        'umid'            => 'UMID',
        'passport'        => 'Passport',
    ];
@endphp

<div class="review-page">
    <div class="review-header">
        <div>
            <h1>Review Credit Application</h1>
            <p class="review-subtitle">Check the applicant's details and documents, then approve or reject.</p>
        </div>
        <span class="status-badge status-{{ $application->status }}">{{ ucfirst($application->status) }}</span>
    </div>

    <div class="review-grid">
        <div class="review-main">
            <section class="review-card">
                <h2>Applicant details</h2>
                <dl class="details-list">
                    <div class="detail"><dt>Applicant</dt><dd>{{ collect([$application->first_name, $application->middle_name, $application->last_name])->filter()->join(' ') }}</dd></div>
                    <div class="detail"><dt>Username</dt><dd>{{ $application->user->username }}</dd></div>
                    <div class="detail"><dt>Email</dt><dd>{{ $application->user->email }}</dd></div>
                    <div class="detail"><dt>Phone</dt><dd>{{ $application->phone_number }}</dd></div>
                    <div class="detail detail-wide"><dt>Address</dt><dd>{{ collect([$application->street, $application->barangay, $application->city, $application->province])->filter()->join(', ') }}</dd></div>
                    <div class="detail">
                        <dt>Monthly due date</dt>
                        <dd>{{ $application->credit_due_day === '15' ? '15th of each month' : 'Last day of each month' }}</dd>
                    </div>
                    <div class="detail"><dt>Monthly income</dt><dd>{{ $application->monthly_income === null ? 'Not provided' : '₱'.number_format((float) $application->monthly_income, 2) }}</dd></div>
                    <div class="detail"><dt>ID type</dt><dd>{{ $idTypes[$application->id_type] ?? \Illuminate\Support\Str::headline((string) $application->id_type) }}</dd></div>
                </dl>
            </section>

            <section class="review-card">
                <h2>Documents</h2>
                <div class="documents">
                    <figure class="document">
                        <figcaption>ID photo</figcaption>
                        <a class="document-frame" href="{{ route('admin.credit-applications.document', [$application, 'id']) }}" target="_blank" rel="noopener" title="Open full size">
                            <img src="{{ route('admin.credit-applications.document', [$application, 'id']) }}" alt="Applicant ID photo">
                        </a>
                    </figure>
                    <figure class="document">
                        <figcaption>Selfie holding ID</figcaption>
                        <a class="document-frame" href="{{ route('admin.credit-applications.document', [$application, 'selfie']) }}" target="_blank" rel="noopener" title="Open full size">
                            <img src="{{ route('admin.credit-applications.document', [$application, 'selfie']) }}" alt="Applicant selfie holding ID">
                        </a>
                    </figure>
                </div>
                <p class="documents-hint">Click a photo to open it full size.</p>
            </section>
        </div>

        <aside class="review-side">
            @if ($application->status === 'pending')
                <section class="review-card">
                    <h2>Approve application</h2>
                    <form method="POST" action="{{ route('admin.credit-applications.approve', $application) }}" class="review-form">
                        @csrf
                        @method('PATCH')
                        <label for="credit_limit">Credit limit (&#8369;)</label>
                        <input id="credit_limit" name="credit_limit" type="number" min="0.01" max="99999999.99" step="0.01" value="5000.00" required>
                        <button type="submit" class="btn btn-approve">Approve</button>
                    </form>
                </section>

                <section class="review-card">
                    <h2>Reject application</h2>
                    <form method="POST" action="{{ route('admin.credit-applications.reject', $application) }}" class="review-form">
                        @csrf
                        @method('PATCH')
                        <label for="rejection_reason">Reason (optional)</label>
                        <textarea id="rejection_reason" name="rejection_reason" maxlength="2000" rows="4"></textarea>
                        <button type="submit" class="btn btn-reject">Reject</button>
                    </form>
                </section>
            @else
                <section class="review-card">
                    <h2>Review result</h2>
                    <dl class="result-list">
                        <div class="detail"><dt>Status</dt><dd>{{ ucfirst($application->status) }}</dd></div>
                        <div class="detail"><dt>Reviewed on</dt><dd>{{ $application->reviewed_at?->format('M j, Y g:i A') ?? 'an unknown date' }}</dd></div>
                    </dl>
                    @if ($application->rejection_reason)
                        <div class="reason-box"><span>Rejection reason</span><p>{{ $application->rejection_reason }}</p></div>
                    @endif
                </section>
            @endif
        </aside>
    </div>
</div>
@endsection
