@extends('layouts.customer')
@section('title', 'Pay Later Payment')
@section('content')
<div style="max-width:620px; margin:32px auto; padding:24px;">
    <section class="card" style="padding:24px; background:white; border-radius:16px;">
        <h1>Pay Later payment</h1>
        <p>Reference: {{ $attempt->reference }}</p>
        <p><strong>&#8369;{{ number_format($attempt->amount_cents / 100, 2) }}</strong></p>
        @error('payment') <p role="alert">{{ $message }}</p> @enderror
        @if ($verificationError) <p role="alert">{{ $verificationError }}</p> @endif
        <p id="payment-status-message" aria-live="polite">
            @switch($attempt->status)
                @case('succeeded')
                    Payment verified. Your installment balance has been updated.
                    @break
                @case('failed')
                    This checkout is closed without a completed payment. Return to Pay Later to try again.
                    @break
                @case('review')
                    This payment needs review. Contact the store with the reference above before paying again.
                    @break
                @case('creating')
                    Your checkout is being prepared. Check again shortly.
                    @break
                @default
                    Payment has not been confirmed. Continue checkout or check its status again.
            @endswitch
        </p>
        <div id="checkout-actions" @if (in_array($attempt->status, ['succeeded', 'failed', 'review'], true)) hidden @endif>
            @if ($attempt->paymongo_checkout_session_id && $attempt->checkout_url)
                <form method="POST" action="{{ route('paylater.payment-attempts.resume', $attempt->reference) }}">
                    @csrf
                    <button type="submit">Continue to PayMongo checkout</button>
                </form>
                <p>If you closed checkout, continue here. Your saved checkout will be reused.</p>
            @elseif ($attempt->paymongo_payment_intent_id)
                <p>This payment used the previous checkout. Check its status or contact the store before paying again.</p>
            @endif
        </div>
        <button id="check-payment" type="button">Check payment status</button>
        <noscript><p><a href="{{ route('paylater.payment-attempts.show', $attempt->reference) }}">Reload to check payment</a></p></noscript>
        <p><a href="{{ route('credit.apply') }}">Back to Pay Later</a></p>
    </section>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const statusUrl = @json(route('paylater.payment-attempts.status', $attempt->reference));
    const message = document.getElementById('payment-status-message');
    const button = document.getElementById('check-payment');
    const actions = document.getElementById('checkout-actions');
    const terminal = ['succeeded', 'failed', 'review'];
    let finished = terminal.includes(@json($attempt->status));
    let checking = false;
    let timer = null;

    async function checkPayment() {
        if (checking) return;
        if (timer) window.clearTimeout(timer);
        checking = true;
        button.disabled = true;
        let delay = 10000;
        try {
            const response = await fetch(statusUrl, {
                headers: { 'Accept': 'application/json' }, credentials: 'same-origin', cache: 'no-store',
            });
            if (response.status === 429) {
                delay = Math.max(60, Number(response.headers.get('Retry-After')) || 60) * 1000;
                throw new Error('Please wait a minute before checking again.');
            }
            if (response.status === 401 || response.status === 419) {
                finished = true;
                throw new Error('Please sign in again to check this payment.');
            }
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'Could not verify payment. Please check again.');
            finished = terminal.includes(data.status);
            actions.hidden = finished;
            message.textContent = {
                succeeded: 'Payment verified. Your installment balance has been updated.',
                failed: 'Checkout is closed without a completed payment. Return to Pay Later to try again.',
                review: 'Payment needs review. Contact the store with your reference before paying again.',
                creating: 'Your checkout is being prepared. Check again shortly.',
                pending: 'Payment has not been confirmed. Continue checkout or check again.',
            }[data.status] || 'Payment has not been confirmed.';
        } catch (error) {
            message.textContent = error.message || 'Could not reach the server. Please check again.';
        } finally {
            checking = false;
            button.disabled = false;
            if (!finished) timer = window.setTimeout(checkPayment, delay);
        }
    }
    button.addEventListener('click', checkPayment);
    if (!finished) timer = window.setTimeout(checkPayment, 10000);
    window.addEventListener('pagehide', () => window.clearTimeout(timer));
});
</script>
@endsection
