@extends('layouts.customer')

@section('title', 'Apply for Pay Later')

@section('content')
<link rel="stylesheet" href="{{ asset('css/credit_apply.css') }}">

<div class="paylater-page">

    <div class="page-head">
        @if ($user->credit_approved)
            <h1>Pay Later</h1>
            <p>Track your credit and pay your monthly balance.</p>
        @else
            <h1>Apply for Pay Later</h1>
            <p>Fill in the form below. We'll review your application and set your credit limit.</p>
        @endif
    </div>

    @if ($user->credit_approved)
    {{-- TODO: Change to view order's installments, due dates, remaining balances, and payment options. --}}
        @php
            $ordersDueThisMonth = $orders->filter(fn ($order) =>
                $order->installments->contains(fn ($installment) =>
                    $installment->due_date->isSameMonth(today())
                    && $installment->balance > 0
                )
            );
        @endphp

        <div class="stat-grid">
            <div class="stat">
                <span class="stat-label">Available credit</span>
                <strong class="stat-value stat-blue">₱{{ number_format((float) $user->credit_limit-$user->unpaidCreditBalance(), 2) }}</strong>
            </div>

            <div class="stat">
                <span class="stat-label">Used credit</span>
                <strong class="stat-value">₱{{ number_format($user->unpaidCreditBalance(), 2) }}</strong>
            </div>

            <div class="stat stat-accent">
                @if ($user->credit_on_hold)
                    <span class="stat-label">Full Pay Later balance due while your account is on hold</span>
                    <strong class="stat-value">₱{{ number_format($user->unpaidCreditBalance(), 2) }}</strong>
                @else
                    <span class="stat-label">Due this month</span>
                    <strong class="stat-value">₱{{ number_format($user->monthlyPayableAmount(), 2) }}</strong>
                @endif
            </div>
        </div>

        @if ($user->credit_on_hold || $user->monthlyPayableAmount() > 0 && !$openPaymentAttempt)
            <section class="card">
                <div class="card-head">
                    <span class="card-icon">
                        <svg viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg>
                    </span>
                    <div>
                        <h2>Make a payment</h2>
                        <p>Choose how you want to pay.</p>
                    </div>
                </div>

                <form
                    id="paylater-payment-form"
                    action="{{ route('paylater.payment-attempts.store') }}"
                    method="POST"
                    class="pay-form"
                >
                    @csrf

                    <div class="field">
                        <label for="paylater-method">Payment method</label>
                        <select id="paylater-method" name="method" required>
                            <option value="qrph">QR Ph</option>
                            <option value="gcash" disabled>GCash</option>
                            <option value="paymaya" disabled>Maya</option>
                        </select>
                    </div>

                    <button id="paylater-submit" type="submit" class="btn-orange">Pay now</button>
                </form>

                <div id="paylater-payment-result" aria-live="polite">
                    <p id="paylater-payment-status"></p>
                    <img id="paylater-qr-image" alt="PayMongo QR Ph payment code" hidden>
                </div>
            </section>
        @endif

        @if ($openPaymentAttempt)
            <div class="notice notice-blue">
                @if ($openPaymentAttempt->status === 'pending' && $openPaymentAttempt->paymongo_payment_intent_id)
                    <p>You have a Pay Later payment in progress.</p>
                    <a href="{{ route('paylater.payment-attempts.show', $openPaymentAttempt->reference) }}">
                        Return to your payment
                    </a>
                @else
                    <p>Your payment is being set up. Refresh this page shortly.</p>
                @endif
            </div>
        @endif

        <h2 class="section-title">Items to Pay</h2>

        @forelse ($ordersDueThisMonth as $order)
            @php
                $monthlyDueForOrder = $order->installments
                    ->filter(fn ($installment) =>
                        $installment->due_date->isSameMonth(today())
                        && $installment->balance > 0
                    )
                    ->sum(fn ($installment) => $installment->balance);
            @endphp

            @if ($monthlyDueForOrder > 0)
                <section class="card">
                    <ul class="pay-items">
                        @foreach ($order->items as $item)
                            <li class="pay-item">
                                <img
                                    src="{{ ($item->variant ?? $item->product)?->image_url ?? asset('images/logo.png') }}"
                                    alt="{{ $item->product?->name ?? 'Product image' }}"
                                    width="60"
                                    height="60"
                                    loading="lazy"
                                >

                                @php
                                    $monthlyDueForOrder = $order->installments
                                        ->filter(fn ($installment) => $installment->due_date->isSameMonth(today()))
                                        ->sum(fn ($installment) => $installment->balance);
                                    $itemSubtotal = (float) $item->price * $item->quantity;
                                    $orderSubtotal = (float) $order->total_amount;
                                    $itemMonthlyPayable = $orderSubtotal > 0
                                        ? round($monthlyDueForOrder * $itemSubtotal / $orderSubtotal, 2)
                                        : 0;
                                    $paidInstallments = $order->installments
                                        ->filter(fn ($installment) =>
                                            round((float) $installment->payments->sum('amount'), 2)
                                                >= (float) $installment->amount_due
                                        )
                                        ->count();

                                    $installmentCount = $order->installments->count();

                                @endphp

                                <div class="pay-item-info">
                                    <span class="pay-item-name">
                                        {{ $item->product?->name ?? 'Product no longer available' }}
                                        @if ($item->variant_name)
                                            ({{ $item->variant_name }})
                                        @endif
                                    </span>
                                    <small>
                                        Installments paid: {{ $paidInstallments }}/{{ $installmentCount }}
                                        &middot; {{ $order->order_date->format('F d') }}
                                    </small>
                                </div>

                                <strong class="pay-item-amount">₱{{ number_format($itemMonthlyPayable, 2) }}</strong>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        @empty
            <div class="notice">
                <p>You don't have any items to pay this month.</p>
            </div>
        @endforelse

    @elseif ($application?->status === 'pending')
        <div class="notice notice-blue">
            <p>Your application is under review. You'll see the Pay Later option after approval.</p>
        </div>

    @else
        @if ($application?->status === 'rejected')
            <div class="notice notice-red">
                <p><strong>Your previous application was rejected.</strong></p>

                @if ($application->rejection_reason)
                    <p>Reason: {{ $application->rejection_reason }}</p>
                @endif

                <p>You may submit a new application below.</p>
            </div>
        @endif

        <div class="apply-layout">

            <form action="{{ route('credit.apply.store') }}"
                  method="POST"
                  enctype="multipart/form-data"
                  class="apply-form">
                @csrf

                <section class="card">
                    <div class="card-head">
                        <span class="card-icon">
                            <svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/></svg>
                        </span>
                        <div>
                            <h2>Personal Information</h2>
                            <p>Please provide your complete and accurate information.</p>
                        </div>
                    </div>

                    <div class="grid grid-3">
                        <div class="field">
                            <label for="first_name">First Name <span class="req">*</span></label>
                            <input id="first_name" name="first_name" type="text" maxlength="150"
                                   placeholder="Enter your first name"
                                   value="{{ old('first_name', $application?->full_name) }}" required>
                        </div>

                        <div class="field">
                            <label for="middle_name">Middle Name <span class="opt">(optional)</span></label>
                            <input id="middle_name" name="middle_name" type="text" maxlength="150"
                                   placeholder="Enter your middle name"
                                   value="{{ old('middle_name', $application?->full_name) }}">
                        </div>

                        <div class="field">
                            <label for="last_name">Last Name <span class="req">*</span></label>
                            <input id="last_name" name="last_name" type="text" maxlength="150"
                                   placeholder="Enter your last name"
                                   value="{{ old('last_name', $application?->full_name) }}" required>
                        </div>
                    </div>

                    <div class="grid grid-2">
                        <div class="field">
                            <label for="phone_number">Phone Number <span class="req">*</span></label>
                            <input id="phone_number" name="phone_number" type="tel" maxlength="11"
                                   placeholder="09XX XXX XXXX"
                                   value="{{ old('phone_number', $application?->phone_number) }}" required>
                        </div>

                        <div class="field">
                            <label for="monthly_income">Monthly Income <span class="req">*</span></label>
                            <input id="monthly_income" name="monthly_income" type="number"
                                   min="0" step="0.01"
                                   placeholder="Enter your monthly income"
                                   value="{{ old('monthly_income', $application?->monthly_income) }}" required>
                        </div>
                    </div>

                    @if ($application?->status === 'rejected' && $application->credit_due_day)
                        <div class="note">
                            Your monthly due date is fixed:
                            <strong>{{ $application->credit_due_day === '15' ? '15th of each month' : 'Last day of each month' }}</strong>.
                        </div>
                        <input type="hidden" name="credit_due_day" value="{{ $application->credit_due_day }}">
                    @else
                        <div class="field field-half">
                            <label for="credit_due_day">Pay Later Due Date <span class="req">*</span></label>
                            <select id="credit_due_day" name="credit_due_day" required>
                                <option value="">Choose your monthly due date</option>
                                <option value="15" @selected(old('credit_due_day', $application?->credit_due_day) === '15')>
                                    15th of each month
                                </option>
                                <option value="last_day" @selected(old('credit_due_day', $application?->credit_due_day) === 'last_day')>
                                    Last day of each month
                                </option>
                            </select>
                        </div>
                    @endif
                </section>

                <section class="card">
                    <div class="card-head">
                        <span class="card-icon">
                            <svg viewBox="0 0 24 24"><path d="M12 21s7-6.2 7-12a7 7 0 1 0-14 0c0 5.8 7 12 7 12z"/><circle cx="12" cy="9" r="2.5"/></svg>
                        </span>
                        <div>
                            <h2>Address Information</h2>
                            <p>Please provide your complete address.</p>
                        </div>
                    </div>

                    <div class="grid grid-3">
                        <div class="field">
                            <label for="province">Province <span class="req">*</span></label>
                            <input id="province" name="province" value="Bulacan" readonly required>
                        </div>

                        <div class="field">
                            <label for="city">City/Municipality <span class="req">*</span></label>
                            <select name="city" id="city">
                                <option value="">Choose a city/municipality</option>
                            </select>
                        </div>

                        <div class="field">
                            <label for="barangay">Barangay <span class="req">*</span></label>
                            <select name="barangay" id="barangay">
                                <option value="">Choose a barangay</option>
                            </select>
                        </div>
                    </div>

                    <div class="field">
                        <label for="street">House Number / Street / Subdivision <span class="req">*</span></label>
                        <input type="text" id="street" name="street"
                               placeholder="e.g. 123 Happy Homes Subdivision"
                               value="{{ old('street', $application?->street) }}"
                               maxlength="255"
                               autocomplete="street-address"
                               required>
                    </div>
                </section>

                <section class="card">
                    <div class="card-head">
                        <span class="card-icon">
                            <svg viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2"/><circle cx="8" cy="12" r="2"/><path d="M13 10h5M13 14h5"/></svg>
                        </span>
                        <div>
                            <h2>Identity Verification</h2>
                            <p>Upload a clear photo of your valid ID and a selfie holding your ID.</p>
                        </div>
                    </div>

                    <div class="field field-half">
                        <label for="id_type">ID Type <span class="req">*</span></label>
                        <select id="id_type" name="id_type" required>
                            <option value="">Choose an ID</option>
                            <option value="national_id" @selected(old('id_type', $application?->id_type) === 'national_id')>National ID</option>
                            <option value="drivers_license" @selected(old('id_type', $application?->id_type) === 'drivers_license')>Driver's License</option>
                            <option value="umid" @selected(old('id_type', $application?->id_type) === 'umid')>UMID</option>
                            <option value="passport" @selected(old('id_type', $application?->id_type) === 'passport')>Passport</option>
                        </select>
                    </div>

                    <div class="grid grid-2">
                        <div class="field">
                            <label for="id_photo">Photo of your ID <span class="req">*</span></label>
                            <small class="hint">(JPG, PNG, or WebP &middot; max 4 MB)</small>

                            <div class="dropzone">
                                <svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="9" cy="11" r="2"/><path d="M14 10h4M14 14h4"/></svg>
                                <span class="dropzone-text">Click to upload or drag and drop</span>
                                <span class="dropzone-button">Choose File</span>
                                <span class="file-name">No file chosen</span>
                                <input id="id_photo" name="id_photo" type="file"
                                       accept=".jpg,.jpeg,.png,.webp" required>
                            </div>
                        </div>

                        <div class="field">
                            <label for="selfie_photo">Selfie holding your ID <span class="req">*</span></label>
                            <small class="hint">(JPG, PNG, or WebP &middot; max 4 MB)</small>

                            <div class="dropzone">
                                <svg viewBox="0 0 24 24"><circle cx="12" cy="9" r="3.5"/><path d="M5 20c0-4 3-6 7-6s7 2 7 6"/></svg>
                                <span class="dropzone-text">Click to upload or drag and drop</span>
                                <span class="dropzone-button">Choose File</span>
                                <span class="file-name">No file chosen</span>
                                <input id="selfie_photo" name="selfie_photo" type="file"
                                       accept=".jpg,.jpeg,.png,.webp" required>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="card">
                    <div class="card-head">
                        <span class="card-icon">
                            <svg viewBox="0 0 24 24"><path d="M7 3h8l4 4v14H7z"/><path d="M15 3v4h4M10 12h6M10 16h6"/></svg>
                        </span>
                        <div>
                            <h2>Terms and Conditions</h2>
                            <p>Please read and agree to the following terms.</p>
                        </div>
                    </div>

                    <details class="accordion">
                        <summary>Rm Lazaro Enterprises Pay Later Terms and Conditions</summary>

                        <div class="accordion-body">
                            <ol>
                                <li>
                                    Submitting an application does not guarantee approval. Rm Lazaro
                                    Enterprises reviews applications and sets an approved credit limit.
                                </li>
                                <li>
                                    In the application, you must choose either the 15th or the last
                                    calendar day of each month as your monthly installment due date.
                                    Your selected due date is fixed after you submit the application and
                                    will be used for your Pay Later installment schedules.
                                </li>
                                <li>
                                    Approved customers may choose a 3, 6, 9, or 12 month plan when
                                    placing an eligible order.
                                </li>
                                <li>
                                    The finance charge is 3.5% per month on the original order total.
                                    It is a flat add-on and is not recalculated against the remaining
                                    balance as installments are paid. The checkout page will show the
                                    order total, finance charge, total payable, installment amounts,
                                    and due dates before the order is placed.
                                </li>
                                <li>
                                    Each installment must be paid by its scheduled due date. If any
                                    installment remains unpaid after its due date, your Pay Later
                                    account will be placed on hold. The full outstanding Pay Later
                                    balance across your orders will then be due, and you cannot place
                                    new Pay Later orders until that balance is fully paid.
                                </li>
                                <li>
                                    No late fee is included in these terms. Returns, cancellations,
                                    and order changes may adjust the balance under Rm Lazaro
                                    Enterprises' return and refund policy.
                                </li>
                            </ol>
                        </div>
                    </details>

                    <label class="consent">
                        <input type="checkbox"
                               name="accept_paylater_terms"
                               value="1"
                               required
                               @checked(old('accept_paylater_terms'))>
                        <span>I have read and agree to the <strong>Pay Later Terms and Conditions</strong>.</span>
                    </label>

                    @error('accept_paylater_terms')
                        <p class="error">{{ $message }}</p>
                    @enderror
                </section>

                <section class="card">
                    <div class="card-head">
                        <span class="card-icon">
                            <svg viewBox="0 0 24 24"><path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/><path d="M9 12l2 2 4-4"/></svg>
                        </span>
                        <div>
                            <h2>Privacy Notice for Pay Later Applications</h2>
                            <p>Please read how your information is handled.</p>
                        </div>
                    </div>

                    <details class="accordion">
                        <summary>Privacy Notice</summary>

                        <div class="accordion-body">
                            <p>
                                <strong>Who is responsible for your information?</strong>
                                Rm Lazaro Enterprises is responsible for the personal information
                                collected for this application. Privacy contact:
                                <strong>rmlazaroenterprises@gmail.com (4XPH+86P, Magsaysay Rd, San Miguel, Bulacan)</strong>.
                            </p>

                            <p>
                                <strong>What information is collected?</strong>
                                This application may collect your name, phone number, address, monthly
                                income, ID type, government ID image, selfie holding your ID, due-date
                                choice, application decision, and information needed to manage your
                                Pay Later account and payments.
                            </p>

                            <p>
                                <strong>Why is it used?</strong>
                                The information is used to verify your identity, review your credit
                                application, set and administer your credit limit, contact you about
                                the application or account, record payments, prevent misuse, and meet
                                applicable recordkeeping requirements.
                            </p>

                            <p>
                                <strong>Lawful basis:</strong>
                                <strong>[confirm and state the applicable lawful basis for each purpose]</strong>.
                                Where processing relies on your consent, that consent is requested
                                separately below.
                            </p>

                            <p>
                                <strong>Who may receive it?</strong>
                                Access is limited to authorized Rm Lazaro Enterprises personnel who
                                review or administer Pay Later accounts. Service providers that host
                                or maintain the application may process information for those
                                services. A payment provider may receive the transaction information
                                needed to process a payment. Information may also be disclosed when
                                required by law. Your ID and selfie are for application review and
                                should not be sent to a payment provider.
                            </p>

                            <p>
                                <strong>How is it protected and retained?</strong>
                                ID and selfie files are kept in private application storage, with access
                                limited to authorized reviewers. Application and account records are
                                retained for
                                <strong>[insert the actual retention period and when it begins]</strong>,
                                then securely deleted or destroyed when no longer needed, unless a
                                longer period is required for a stated legal or business purpose.
                            </p>

                            <p>
                                <strong>Your rights and questions:</strong>
                                You may contact us using the privacy contact above to ask about your
                                information or exercise applicable rights, including access or
                                correction. You may also raise a concern with the National Privacy
                                Commission. See its
                                <a href="https://privacy.gov.ph/data-subject-rights/" target="_blank" rel="noopener noreferrer">
                                    data subject rights information
                                </a>.
                            </p>
                        </div>
                    </details>

                    <label class="consent">
                        <input type="checkbox"
                               name="accept_credit_privacy"
                               value="1"
                               required
                               @checked(old('accept_credit_privacy'))>
                        <span>
                            I have read this Privacy Notice. I consent to the stated processing
                            of my application information, including my ID image and selfie,
                            where consent is the applicable basis.
                        </span>
                    </label>

                    @error('accept_credit_privacy')
                        <p class="error">{{ $message }}</p>
                    @enderror
                </section>

                <button type="submit" class="btn-orange btn-submit">
                    <svg viewBox="0 0 24 24"><path d="M22 2L11 13"/><path d="M22 2l-7 20-4-9-9-4z"/></svg>
                    Submit Application
                </button>
            </form>

            <aside class="apply-side">
                <div class="card">
                    <h3>How Pay Later works</h3>

                    <ol class="steps">
                        <li><strong>Apply</strong><span>Submit this form with your ID and selfie.</span></li>
                        <li><strong>Get reviewed</strong><span>We check your application and set your credit limit.</span></li>
                        <li><strong>Choose a plan</strong><span>Pick 3, 6, 9, or 12 months at checkout.</span></li>
                        <li><strong>Pay monthly</strong><span>Pay each installment on your chosen due date.</span></li>
                    </ol>
                </div>

                <div class="card side-note">
                    <h3>Finance charge</h3>
                    <p>
                        3.5% per month on the original order total. The checkout page shows
                        the full amount payable before you place your order.
                    </p>
                </div>
            </aside>
        </div>
    @endif
</div>

<script>
document.querySelectorAll('.dropzone input[type="file"]').forEach(input => {
    input.addEventListener('change', () => {
        const name = input.files.length ? input.files[0].name : 'No file chosen';
        input.closest('.dropzone').querySelector('.file-name').textContent = name;
    });
});
</script>

<script>
fetch('/data/bulacan-address.json')
    .then(response => response.json())
    .then(data => {
        const citySelect = document.getElementById('city');
        const barangaySelect = document.getElementById('barangay');

        const selectedCity = @json(old('city', $application?->city));
        const selectedBarangay = @json(old('barangay', $application?->barangay));

    function loadBarangays(cityName) {
        barangaySelect.replaceChildren(new Option('Choose a barangay', ''));
        const city = data.cities.find(item => item.name === cityName);

        if (!city) {
                barangaySelect.disabled = true;
                return;
            }

            city.barangays.forEach(barangayName => {
                const option = new Option(barangayName, barangayName);
                option.selected = barangayName === selectedBarangay;
                barangaySelect.appendChild(option);
            });

            barangaySelect.disabled = false;
        }

        data.cities.forEach(city => {
            const option = new Option(city.name, city.name);
            option.selected = city.name === selectedCity;
            citySelect.appendChild(option);
        });

        loadBarangays(selectedCity);
        citySelect.addEventListener('change', () => {
            loadBarangays(citySelect.value);
        });
    })
    .catch(error => {
        console.error('Could not load the Bulacan address list:', error);
    });
</script>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('paylater-payment-form');

    if (!form) {
        return;
    }

    const methodSelect = document.getElementById('paylater-method');
    const submitButton = document.getElementById('paylater-submit');
    const statusText = document.getElementById('paylater-payment-status');
    const qrImage = document.getElementById('paylater-qr-image');

    const createAttemptUrl = @json(route('paylater.payment-attempts.store'));
    const statusUrlTemplate = @json(route(
        'paylater.payment-attempts.status',
        ['reference' => '__REFERENCE__']
    ));
    const paymentPageUrlTemplate = @json(route(
        'paylater.payment-attempts.show',
        ['reference' => '__REFERENCE__']
    ));

    function paymentPageUrl(reference) {
        return paymentPageUrlTemplate.replace(
            '__REFERENCE__',
            encodeURIComponent(reference)
        );
    }

    async function readJson(response) {
        const data = await response.json();

        if (!response.ok) {
            const validationMessage = data.errors
                ? Object.values(data.errors).flat()[0]
                : null;

            throw new Error(data.message || validationMessage || 'Payment request failed.');
        }

        return data;
    }

    function removeAttemptFromUrl() {
        const url = new URL(window.location.href);
        url.searchParams.delete('paylater_attempt');
        window.history.replaceState({}, '', url);
    }

    async function pollPaymentStatus(reference) {
        const statusUrl = statusUrlTemplate.replace(
            '__REFERENCE__',
            encodeURIComponent(reference)
        );

        statusText.textContent = 'Waiting for payment confirmation…';

        while (true) {
            const response = await fetch(statusUrl, {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin',
            });

            const data = await response.json();

            if (data.status === 'succeeded') {
                statusText.textContent = 'Payment confirmed. Updating your balance…';
                removeAttemptFromUrl();
                window.setTimeout(() => window.location.reload(), 1200);
                return;
            }

            if (data.status === 'failed') {
                statusText.textContent = 'Payment was not completed. You can start another attempt.';
                removeAttemptFromUrl();
                submitButton.disabled = false;
                return;
            }

            if (data.status === 'review') {
                statusText.textContent = 'Payment needs review. Please contact the store.';
                return;
            }

            if (!response.ok) {
                throw new Error(data.message || 'Could not check payment status.');
            }

            await new Promise(resolve => window.setTimeout(resolve, 5000));
        }
    }

    form.addEventListener('submit', async event => {
        event.preventDefault();
        submitButton.disabled = true;
        statusText.textContent = 'Starting payment…';
        qrImage.hidden = true;

        let reference = null;

        try {
            // Ask Laravel to calculate the amount and create the Payment Intent.
            const attemptResponse = await fetch(createAttemptUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': form.querySelector('[name="_token"]').value,
                },
                credentials: 'same-origin',
                body: JSON.stringify({ method: methodSelect.value }),
            });

            const attempt = await readJson(attemptResponse);
            reference = attempt.reference;

            const paymentUrl = paymentPageUrl(reference);
            const returnUrl = new URL(paymentUrl, window.location.origin);


            // Create a PayMongo payment method with the browser-safe public key.
            const methodResponse = await fetch('https://api.paymongo.com/v1/payment_methods', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'Authorization': 'Basic ' + btoa(attempt.public_key + ':'),
                },
                body: JSON.stringify({
                    data: {
                        attributes: { type: attempt.method },
                    },
                }),
            });

            const paymentMethodData = await readJson(methodResponse);
            const paymentMethodId = paymentMethodData.data.id;

            // Attach the method to the intent using its short-lived client key.
            const attachResponse = await fetch(
                `https://api.paymongo.com/v1/payment_intents/${encodeURIComponent(attempt.payment_intent_id)}/attach`,
                {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'Authorization': 'Basic ' + btoa(attempt.public_key + ':'),
                    },
                    body: JSON.stringify({
                        data: {
                            attributes: {
                                payment_method: paymentMethodId,
                                client_key: attempt.client_key,
                                return_url: returnUrl.toString(),
                            },
                        },
                    })
                }
            );

            const attached = await readJson(attachResponse);
            const nextAction = attached.data.attributes.next_action;

            // Wallets send the customer to GCash or Maya.
            if (attempt.method === 'gcash' || attempt.method === 'paymaya') {
                const redirectUrl = nextAction?.redirect?.url;

                if (!redirectUrl) {
                    throw new Error('PayMongo did not return a wallet redirect URL.');
                }

                window.location.assign(redirectUrl);
                return;
            }

            // QR Ph returns an image for the customer to scan.
            const imageUrl = nextAction?.code?.image_url;

            if (!imageUrl) {
                throw new Error('PayMongo did not return a QR image.');
            }

            qrImage.src = imageUrl;
            qrImage.hidden = false;

            window.location.assign(paymentUrl);
            return;
        } catch (error) {
            statusText.textContent = error.message || 'Could not start the payment.';

            // If Laravel created an attempt but the browser step failed,
            // ask the server to check it before allowing another attempt.
            if (reference) {
                try {
                    await pollPaymentStatus(reference);
                } catch {
                    statusText.textContent += ' Refresh this page to check it again.';
                }
            } else {
                submitButton.disabled = false;
            }
        }
    });

    const returnedAttempt = new URLSearchParams(window.location.search)
        .get('paylater_attempt');

    if (returnedAttempt) {
        submitButton.disabled = true;
        pollPaymentStatus(returnedAttempt).catch(error => {
            statusText.textContent = error.message || 'Could not check payment status.';
        });
    }
});
</script>

@endsection