<?php

namespace App;

use App\Models\CreditInstallment;
use App\Models\CreditPayment;
use App\Models\CreditPaymentAttempt;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class PayLaterPayments
{
    public function start(User $customer): CreditPaymentAttempt
    {
        $this->secretKey();
        [$attempt, $created] = DB::transaction(function () use ($customer) {
            $user = User::query()->lockForUpdate()->findOrFail($customer->id);
            abort_unless($user->credit_approved || $user->unpaidCreditBalance() > 0, 403);
            $existing = $user->creditPaymentAttempts()->whereIn('status', ['creating', 'pending', 'review'])
                ->lockForUpdate()->latest('id')->first();
            if ($existing) {
                return [$existing, false];
            }
            $user->refreshCreditHold();
            $allocations = CreditInstallment::query()
                ->whereHas('order', fn ($query) => $query->where('user_id', $user->id)
                    ->where('payment_method', 'paylater')->where('order_status', '!=', 'cancelled'))
                ->lockForUpdate()->get()
                ->filter(fn (CreditInstallment $installment) => $installment->balance > 0
                    && ($user->credit_on_hold || $installment->due_date->isSameMonth(today())))
                ->map(fn (CreditInstallment $installment) => [
                    'credit_installment_id' => $installment->id,
                    'amount_cents' => (int) round($installment->balance * 100),
                ])->filter(fn (array $allocation) => $allocation['amount_cents'] > 0)->values();
            if ($allocations->sum('amount_cents') < 100) {
                throw ValidationException::withMessages(['payment' => 'There is no Pay Later balance currently due.']);
            }
            $attempt = $user->creditPaymentAttempts()->create([
                'reference' => (string) Str::uuid(), 'method' => 'qrph',
                'amount_cents' => $allocations->sum('amount_cents'), 'currency' => 'PHP', 'status' => 'creating',
            ]);
            $attempt->allocations()->createMany($allocations->all());

            return [$attempt, true];
        });
        if (! $created) {
            return $this->sync($attempt);
        }
        try {
            $response = Http::withBasicAuth($this->secretKey(), '')->acceptJson()->timeout(20)
                ->post('https://api.paymongo.com/v2/checkout_sessions', [
                    'data' => ['attributes' => [
                        'line_items' => [[
                            'name' => 'Pay Later payment', 'amount' => $attempt->amount_cents,
                            'currency' => $attempt->currency, 'quantity' => 1,
                        ]],
                        'payment_method_types' => ['qrph'], 'reference_number' => $attempt->reference,
                        'description' => 'Pay Later payment '.$attempt->reference,
                        'success_url' => route('paylater.payment-attempts.show', $attempt->reference),
                        'cancel_url' => route('paylater.payment-attempts.show', $attempt->reference),
                        'pass_on_fees' => false,
                    ]],
                ]);
        } catch (Throwable) {
            $attempt->update(['status' => 'review']);
            throw new RuntimeException('Checkout creation could not be confirmed. Please check your saved payment.');
        }
        if (! $response->successful()) {
            $attempt->update(['status' => in_array($response->status(), [400, 401, 403, 422], true) ? 'failed' : 'review']);
            throw new RuntimeException('PayMongo could not start checkout. Please check your saved payment.');
        }
        $session = $response->json('data', []);
        $sessionId = data_get($session, 'id');
        $url = data_get($session, 'attributes.checkout_url');
        $attempt->update([
            'paymongo_checkout_session_id' => is_string($sessionId) && str_starts_with($sessionId, 'cs_') ? $sessionId : null,
            'checkout_url' => $this->validCheckoutUrl($url) ? $url : null, 'status' => 'review',
        ]);
        if (! $attempt->paymongo_checkout_session_id || ! $attempt->checkout_url) {
            throw new RuntimeException('PayMongo returned unexpected checkout details. Please contact the store.');
        }
        $attempt->update(['status' => 'pending']);

        return $this->sync($attempt);
    }

    public function sync(CreditPaymentAttempt $attempt): CreditPaymentAttempt
    {
        if ($attempt->status === 'succeeded') {
            return $attempt;
        }
        if (! $attempt->paymongo_checkout_session_id && ! $attempt->paymongo_payment_intent_id) {
            if ($attempt->status === 'creating' && $attempt->created_at->lt(now()->subMinutes(5))) {
                return $this->recordResult($attempt, 'review');
            }
            $attempt->update(['last_checked_at' => now()]);

            return $attempt;
        }
        $isCheckout = filled($attempt->paymongo_checkout_session_id);
        $resource = $isCheckout ? 'checkout_sessions' : 'payment_intents';
        $id = $isCheckout ? $attempt->paymongo_checkout_session_id : $attempt->paymongo_payment_intent_id;
        $response = Http::withBasicAuth($this->secretKey(), '')->acceptJson()->timeout(20)
            ->get('https://api.paymongo.com/v1/'.$resource.'/'.rawurlencode($id));
        if (! $response->successful()) {
            throw new RuntimeException('Could not verify payment with PayMongo. Please check again.');
        }
        $data = $response->json('data', []);
        $attributes = data_get($data, 'attributes', []);
        if ($isCheckout) {
            if (! $this->sessionMatches($attempt, $data)) {
                return $this->recordResult($attempt, 'review');
            }
            if ($this->validCheckoutUrl(data_get($attributes, 'checkout_url'))) {
                $attempt->update(['checkout_url' => data_get($attributes, 'checkout_url')]);
            }
            $intent = data_get($attributes, 'payment_intent');
        } else {
            $intent = $data;
            if (data_get($intent, 'id') !== $attempt->paymongo_payment_intent_id || ! $this->intentMatches($attempt, $intent)) {
                return $this->recordResult($attempt, 'review');
            }
        }
        $payments = collect(data_get($attributes, 'payments', []))
            ->filter(fn (array $payment) => data_get($payment, 'attributes.status') === 'paid')->values();
        if ($payments->isNotEmpty()) {
            $payment = $payments->first();
            $matches = $payments->count() === 1 && $this->intentMatches($attempt, $intent)
                && data_get($intent, 'attributes.status') === 'succeeded'
                && is_string(data_get($payment, 'id')) && str_starts_with($payment['id'], 'pay_')
                && data_get($payment, 'attributes.payment_intent_id') === data_get($intent, 'id')
                && data_get($payment, 'attributes.amount') === $attempt->amount_cents
                && data_get($payment, 'attributes.currency') === $attempt->currency
                && data_get($payment, 'attributes.livemode') === $this->liveMode()
                && data_get($payment, 'attributes.source.type') === $attempt->method
                && empty(data_get($payment, 'attributes.refunds', []));

            return $this->recordResult($attempt, $matches ? 'succeeded' : 'review', $payment, is_array($intent) ? $intent : []);
        }
        $status = $isCheckout && data_get($attributes, 'status') === 'expired' ? 'failed'
            : (! $isCheckout && data_get($attributes, 'status') === 'cancelled' ? 'failed' : 'pending');

        return $this->recordResult($attempt, $status);
    }

    public function validCheckoutUrl(mixed $url): bool
    {
        return is_string($url) && filter_var($url, FILTER_VALIDATE_URL)
            && parse_url($url, PHP_URL_SCHEME) === 'https'
            && parse_url($url, PHP_URL_HOST) === 'checkout.paymongo.com'
            && parse_url($url, PHP_URL_USER) === null && parse_url($url, PHP_URL_PASS) === null
            && in_array(parse_url($url, PHP_URL_PORT), [null, 443], true);
    }

    private function sessionMatches(CreditPaymentAttempt $attempt, array $session): bool
    {
        $items = collect(data_get($session, 'attributes.line_items', []));

        return data_get($session, 'id') === $attempt->paymongo_checkout_session_id
            && data_get($session, 'type') === 'checkout_session'
            && data_get($session, 'attributes.reference_number') === $attempt->reference
            && data_get($session, 'attributes.livemode') === $this->liveMode()
            && data_get($session, 'attributes.payment_method_types') === [$attempt->method]
            && $items->isNotEmpty()
            && $items->every(fn (array $item) => ($item['currency'] ?? null) === $attempt->currency
                && is_int($item['amount'] ?? null) && $item['amount'] > 0
                && is_int($item['quantity'] ?? null) && $item['quantity'] > 0)
            && $items->sum(fn (array $item) => $item['amount'] * $item['quantity']) === $attempt->amount_cents;
    }

    private function intentMatches(CreditPaymentAttempt $attempt, mixed $intent): bool
    {
        return is_array($intent) && is_string(data_get($intent, 'id')) && str_starts_with($intent['id'], 'pi_')
            && data_get($intent, 'attributes.amount') === $attempt->amount_cents
            && data_get($intent, 'attributes.currency') === $attempt->currency
            && data_get($intent, 'attributes.livemode') === $this->liveMode();
    }

    private function recordResult(CreditPaymentAttempt $attempt, string $status, array $payment = [], array $intent = []): CreditPaymentAttempt
    {
        return DB::transaction(function () use ($attempt, $status, $payment, $intent) {
            $user = User::query()->lockForUpdate()->findOrFail($attempt->user_id);
            $locked = $user->creditPaymentAttempts()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === 'succeeded') {
                return $locked;
            }
            if ($locked->status === 'failed' && $status === 'pending') {
                return $locked;
            }
            $locked->last_checked_at = now();
            if ($status !== 'succeeded') {
                $locked->status = $status;
                $locked->save();

                return $locked;
            }
            $allocations = $locked->allocations()->orderBy('credit_installment_id')->lockForUpdate()->get();
            $reusedPayment = CreditPaymentAttempt::query()->where('paymongo_payment_id', $payment['id'])->whereKeyNot($locked->id)->exists();
            if ($reusedPayment || $allocations->isEmpty() || (int) $allocations->sum('amount_cents') !== $locked->amount_cents) {
                $locked->status = 'review';
                $locked->save();

                return $locked;
            }
            $entries = [];
            foreach ($allocations as $allocation) {
                $installment = CreditInstallment::query()->lockForUpdate()->find($allocation->credit_installment_id);
                $order = $installment ? Order::query()->lockForUpdate()->find($installment->order_id) : null;
                if (! $order || $order->user_id !== $user->id || $order->payment_method !== 'paylater'
                    || $order->order_status === 'cancelled'
                    || (int) round($installment->balance * 100) !== $allocation->amount_cents) {
                    $locked->status = 'review';
                    $locked->save();

                    return $locked;
                }
                $entries[] = [$installment, $order, $allocation];
            }
            $paidAt = is_numeric(data_get($payment, 'attributes.paid_at'))
                ? Carbon::createFromTimestamp((int) data_get($payment, 'attributes.paid_at'), config('app.timezone')) : now();
            foreach ($entries as [$installment, $order, $allocation]) {
                if (! $user->credit_on_hold && $installment->due_date->isBefore($paidAt->copy()->startOfDay())) {
                    $user->credit_on_hold = true;
                    $user->save();
                }
                CreditPayment::create([
                    'order_id' => $order->id, 'credit_installment_id' => $installment->id,
                    'amount' => $allocation->amount_cents / 100, 'paid_at' => $paidAt,
                    'note' => 'PayMongo payment '.$locked->reference,
                ]);
            }
            $locked->fill([
                'status' => 'succeeded', 'paymongo_payment_intent_id' => $intent['id'],
                'paymongo_payment_id' => $payment['id'], 'paid_at' => $paidAt,
            ])->save();
            $user->refreshCreditHold();

            return $locked;
        });
    }

    private function secretKey(): string
    {
        $key = (string) config('services.paymongo.secret');
        if (! str_starts_with($key, 'sk_test_') && ! str_starts_with($key, 'sk_live_')) {
            throw new RuntimeException('PayMongo is not configured.');
        }

        return $key;
    }

    private function liveMode(): bool
    {
        return str_starts_with($this->secretKey(), 'sk_live_');
    }
}
