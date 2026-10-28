@extends('layouts.customer')
 
@section('title', 'Apply for Pay Later')

@section('content')
<div class="cart-page">
    @if ($user->credit_approved)
        <h1 class="cart-title">Pay Later</h1> 
    @else
        <h1 class="cart-title">Apply for Pay Later</h1>
    @endif

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

        <div>
            <p>Available credit</p>
            <h3> ₱{{ number_format((float) $user->credit_limit-$user->unpaidCreditBalance(), 2) }}</h3>
            <p>Used credit</p>
            <p> ₱{{ number_format($user->unpaidCreditBalance(), 2) }}</p>
            @if ($user->credit_on_hold)
                <p>Full Pay Later balance due while your account is on hold</p>
                <h3>₱{{ number_format($user->unpaidCreditBalance(), 2) }}</h3>
            @else
                <p>Due this month</p>
                <h3>₱{{ number_format($user->monthlyPayableAmount(), 2) }}</h3>
            @endif
            @if ($user->credit_on_hold || $user->monthlyPayableAmount() > 0 && !$openPaymentAttempt)
                <form
                    id="paylater-payment-form"
                    action="{{ route('paylater.payment-attempts.store') }}"
                    method="POST"
                >
                    @csrf

                    <label for="paylater-method">Payment method</label>
                    <select id="paylater-method" name="method" required>
                        <option value="qrph">QR Ph</option>
                        <option value="gcash" disabled>GCash</option>
                        <option value="paymaya" disabled>Maya</option>
                    </select>
                
                    <button id="paylater-submit" type="submit">Pay now</button>
                </form>
            
                <div id="paylater-payment-result" aria-live="polite">
                    <p id="paylater-payment-status"></p>
                    <img id="paylater-qr-image" alt="PayMongo QR Ph payment code" hidden>
                </div>
            @endif
            @if ($openPaymentAttempt)
                @if ($openPaymentAttempt->status === 'pending' && $openPaymentAttempt->paymongo_payment_intent_id)
                    <p>You have a Pay Later payment in progress.</p>
                    <a href="{{ route('paylater.payment-attempts.show', $openPaymentAttempt->reference) }}">
                        Return to your payment
                    </a>
                @else
                    <p>Your payment is being set up. Refresh this page shortly.</p>
                @endif
            @endif
        </div>
        
        <h1 class="cart-title">Items to Pay</h1>


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
                <section>
                        <ul>
                        @foreach ($order->items as $item)
                            <div>
                                <img
                                    src="{{ ($item->variant ?? $item->product)?->image_url ?? asset('images/logo.png') }}"
                                    alt="{{ $item->product?->name ?? 'Product image' }}"
                                    width="60"
                                    height="60"
                                    loading="lazy"
                                >
                                {{ $item->product?->name ?? 'Product no longer available' }}
                                @if ($item->variant_name)
                                    ({{ $item->variant_name }})
                                @endif
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
                                ₱{{ number_format($itemMonthlyPayable, 2) }}
                                
                                <small>
                                    Installments paid: {{ $paidInstallments }}/{{ $installmentCount }}
                                    {{ $order->order_date->format('F d') }}
                                </small>
                            </div>
                        @endforeach
                        </ul>
                </section>
            @endif 
        @empty
            <p>You don't have any items to pay this month.</p>
        @endforelse
    @elseif ($application?->status === 'pending')
        <p>Your application is under review. You'll see the Pay Later option after approval.</p>

    @else
        @if ($application?->status === 'rejected')
            <p>Your previous application was rejected.</p>

            @if ($application->rejection_reason)
                <p>Reason: {{ $application->rejection_reason }}</p>
            @endif

            <p>You may submit a new application below.</p>
        @endif

        <form action="{{ route('credit.apply.store') }}"
              method="POST"
              enctype="multipart/form-data">
            @csrf

            <div>
                <label for="first_name">First Name</label>
                <input id="first_name" name="first_name" type="text" maxlength="150"
                       value="{{ old('first_name', $application?->full_name) }}" required>

                <label for="middle_name">Middle Name(optional)</label>
                <input id="middle_name" name="middle_name" type="text" maxlength="150"
                       value="{{ old('middle_name', $application?->full_name) }}">

                <label for="last_name">Last Name</label>
                <input id="last_name" name="last_name" type="text" maxlength="150"
                       value="{{ old('last_name', $application?->full_name) }}" required>
            </div>

            <div>
                <label for="phone_number">Phone number</label>
                <input id="phone_number" name="phone_number" type="tel" maxlength="11"
                       value="{{ old('phone_number', $application?->phone_number) }}" required>
            </div>

            <div>
                <label for="province">Province</label>
                <input id="province" name="province" value="Bulacan" readonly required>
                
                <label for="city">City/Municipality</label>
                <select name="city" id="city">
                    <option value="">Choose a city/municipality</option>
                </select>
                
                <label for="barangay">Barangay</label>
                <select name="barangay" id="barangay">
                    <option value="">Choose a barangay</option>
                </select>
                
                <label for="street">House number / street / subdivision</label>
                <input type="text" id="street" name="street" 
                    value="{{ old('street', $application?->street) }}"
                    maxlength="255" 
                    autocomplete="street-address" 
                    required>
            </div>

            <div>
                <label for="monthly_income">Monthly income</label>
                <input id="monthly_income" name="monthly_income" type="number"
                       min="0" step="0.01"
                       value="{{ old('monthly_income', $application?->monthly_income) }}" required>
            </div>

            <div>
                @if ($application?->status === 'rejected' && $application->credit_due_day)
                    <p>
                        Your monthly due date is fixed:
                        {{ $application->credit_due_day === '15' ? '15th of each month' : 'Last day of each month' }}.
                    </p>
                    <input type="hidden" name="credit_due_day" value="{{ $application->credit_due_day }}">
                @else
                    <label for="credit_due_day">Pay Later monthly due date</label>
                    <select id="credit_due_day" name="credit_due_day" required>
                        <option value="">Choose your monthly due date</option>
                        <option value="15" @selected(old('credit_due_day', $application?->credit_due_day) === '15')>
                            15th of each month
                        </option>
                        <option value="last_day" @selected(old('credit_due_day', $application?->credit_due_day) === 'last_day')>
                            Last day of each month
                        </option>
                    </select>
                @endif
            </div>

            <div>
                <label for="id_type">ID type</label>
                <select id="id_type" name="id_type" required>
                    <option value="">Choose an ID</option>
                    <option value="national_id" @selected(old('id_type', $application?->id_type) === 'national_id')>National ID</option>
                    <option value="drivers_license" @selected(old('id_type', $application?->id_type) === 'drivers_license')>Driver's License</option>
                    <option value="umid" @selected(old('id_type', $application?->id_type) === 'umid')>UMID</option>
                    <option value="passport" @selected(old('id_type', $application?->id_type) === 'passport')>Passport</option>
                </select>
            </div>

            <div>
                <label for="id_photo">Photo of your ID (JPG, PNG, or WebP; max 4 MB)</label>
                <input id="id_photo" name="id_photo" type="file"
                       accept=".jpg,.jpeg,.png,.webp" required>
            </div>

            <div>
                <label for="selfie_photo">Selfie holding your ID (max 4 MB)</label>
                <input id="selfie_photo" name="selfie_photo" type="file"
                       accept=".jpg,.jpeg,.png,.webp" required>
            </div>

            <section>
                <h2>Rm Lazaro Enterprises Pay Later Terms and Conditions</h2>

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
            
                <label>
                    <input type="checkbox"
                           name="accept_paylater_terms"
                           value="1"
                           required
                           @checked(old('accept_paylater_terms'))>
                    I have read and agree to these Pay Later Terms and Conditions.
                </label>
            
                @error('accept_paylater_terms')
                    <p class="error">{{ $message }}</p>
                @enderror
            </section>

            <section>
                <h2>Privacy Notice for Pay Later Applications</h2>
            
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
            
                <label>
                    <input type="checkbox"
                           name="accept_credit_privacy"
                           value="1"
                           required
                           @checked(old('accept_credit_privacy'))>
                    I have read this Privacy Notice. I consent to the stated processing
                    of my application information, including my ID image and selfie,
                    where consent is the applicable basis.
                </label>
            
                @error('accept_credit_privacy')
                    <p class="error">{{ $message }}</p>
                @enderror
            </section>

            <button type="submit">Submit application</button>
        </form>
    @endif
</div>

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