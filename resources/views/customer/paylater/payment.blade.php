@extends('layouts.customer')

@section('title', 'Pay Later Payment')

@section('content')
@php
    $methodName = match ($attempt->method) {
        'qrph' => 'QR Ph',
        'gcash' => 'GCash',
        'paymaya' => 'Maya',
        default => ucfirst($attempt->method),
    };

    $qrImage = data_get($attributes, 'next_action.code.image_url');
    $redirectUrl = data_get($attributes, 'next_action.redirect.url');
@endphp

<style>
    .paylater-payment-page {
        max-width: 620px;
        margin: 32px auto;
        padding: 24px;
    }

    .paylater-payment-card {
        padding: 24px;
        border-radius: 16px;
        background: #fff;
        box-shadow: 0 8px 28px rgba(20, 40, 80, 0.12);
        text-align: center;
    }

    .paylater-payment-qr {
        display: block;
        width: min(100%, 360px);
        height: auto;
        margin: 24px auto;
    }

    .paylater-payment-amount {
        font-size: 1.6rem;
        font-weight: 700;
    }

    .paylater-payment-status {
        margin-top: 16px;
    }

    .paylater-payment-button {
        display: inline-block;
        margin-top: 18px;
        padding: 12px 20px;
        border-radius: 8px;
        background: #173d7a;
        color: white;
        text-decoration: none;
    }
</style>

<div class="paylater-payment-page">
    <section class="paylater-payment-card">
        <h1>Complete your Pay Later payment</h1>

        <p>Payment method: {{ $methodName }}</p>
        <p class="paylater-payment-amount">
            ₱{{ number_format($attempt->amount_cents / 100, 2) }}
        </p>

        @if ($attempt->expires_at)
            <p>
                Time remaining:
                <strong id="payment-countdown">Calculating…</strong>
            </p>
        @endif
                @if ($providerStatus === 'succeeded')
            <p>Payment confirmed. This attempt is complete.</p>
        @elseif ($attempt->method === 'qrph' && $qrImage)
            <p>Scan this QR code using your bank or wallet app.</p>
            <img class="paylater-payment-qr" src="{{ $qrImage }}" alt="QR Ph payment code">
        @elseif ($redirectUrl)
            <p>Continue to {{ $methodName }} to authorize your payment.</p>
            <a class="paylater-payment-button" href="{{ $redirectUrl }}">
                Continue to {{ $methodName }}
            </a>
        @elseif ($providerStatus === 'failed')
            <p>This payment attempt was not completed. Return to Pay Later to try again.</p>
        @elseif ($providerStatus === 'review')
            <p>This payment needs review. Please contact the store.</p>
        @else
            <p>Payment is being processed or its payment details are unavailable.</p>
        @endif
        <p id="payment-status-message" class="paylater-payment-status" aria-live="polite">
            Checking payment status…
        </p>

        <a href="{{ route('credit.apply') }}">Back to Pay Later</a>
    </section>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const statusUrl = @json(route('paylater.payment-attempts.status', $attempt->reference));
    const countdown = document.getElementById('payment-countdown');
    const statusMessage = document.getElementById('payment-status-message');
    const expiresAt = @json($expiresAt ? $expiresAt->getTimestamp() * 1000 : null);

    let polling = false;
    let pollTimer = null;
    let countdownTimer = null;

    async function checkStatus() {
        if (polling) return;
        polling = true;

        try {
            const response = await fetch(statusUrl, {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin',
            });

            const data = await response.json();

            if (data.status === 'succeeded') {
                statusMessage.textContent = 'Payment confirmed.';
                if (pollTimer) window.clearInterval(pollTimer);
                return;
            }

            if (data.status === 'failed') {
                statusMessage.textContent = 'Payment was not completed. You can start another attempt.';
                if (pollTimer) window.clearInterval(pollTimer);
                return;
            }

            if (data.status === 'review') {
                statusMessage.textContent = 'Payment needs review. Please contact the store.';
                if (pollTimer) window.clearInterval(pollTimer);
                return;
            }

            if (!response.ok) {
                statusMessage.textContent = data.message || 'Could not check payment status.';
            } else {
                statusMessage.textContent = 'Waiting for PayMongo to confirm the payment…';
            }
        } catch {
            statusMessage.textContent = 'Could not reach the server to check payment status.';
        } finally {
            polling = false;
        }
    }

    function updateCountdown() {
        if (!countdown || !expiresAt) return;

        const secondsLeft = Math.max(0, Math.floor((expiresAt - Date.now()) / 1000));

        if (secondsLeft === 0) {
            countdown.textContent = 'Time limit reached; checking payment status…';
            if (countdownTimer) {
                window.clearInterval(countdownTimer);
                countdownTimer = null;
            }
            return;
        }

        const hours = Math.floor(secondsLeft / 3600);
        const minutes = Math.floor((secondsLeft % 3600) / 60);
        const seconds = secondsLeft % 60;

        countdown.textContent =
            `${String(hours).padStart(2, '0')}:` +
            `${String(minutes).padStart(2, '0')}:` +
            `${String(seconds).padStart(2, '0')}`;
    }

    updateCountdown();
    countdownTimer = window.setInterval(updateCountdown, 1000);

    checkStatus();
    pollTimer = window.setInterval(checkStatus, 5000);
});
</script>
@endsection