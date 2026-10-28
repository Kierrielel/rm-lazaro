<?php

namespace App\Http\Controllers;

use App\Models\CreditPayment;
use App\Models\Order;
use App\Models\CreditInstallment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Carbon;
use Throwable;

class PayLaterPaymentController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'method' => ['required', Rule::in(['qrph', 'gcash', 'paymaya'])],
        ]);

        $secretKey = config('services.paymongo.secret');

        if (blank($secretKey)) {
            return response()->json([
                'message' => 'PayMongo is not configured.',
            ], 503);
        }

        $attempt = DB::transaction(function () use ($request, $validated) {
            $user = User::query()
                ->lockForUpdate()
                ->findOrFail($request->user()->id);

            abort_unless($user->credit_approved, 403);

            $user->refreshCreditHold();

            $hasOpenAttempt = $user->creditPaymentAttempts()
                ->whereIn('status', ['creating', 'pending'])
                ->exists();

            if ($hasOpenAttempt) {
                throw ValidationException::withMessages([
                    'payment' => 'You already have a Pay Later payment in progress.',
                ]);
            }

            $installments = CreditInstallment::query()
                ->whereHas('order', function ($query) use ($user) {
                    $query->where('user_id', $user->id)
                        ->where('payment_method', 'paylater')
                        ->where('order_status', '!=', 'cancelled');
                })
                ->with('payments')
                ->lockForUpdate()
                ->get()
                ->filter(function (CreditInstallment $installment) use ($user) {
                    return $installment->balance > 0
                        && (
                            $user->credit_on_hold
                            || $installment->due_date->isSameMonth(today())
                        );
                })
                ->values();

            $allocations = $installments
                ->map(fn(CreditInstallment $installment) => [
                    'credit_installment_id' => $installment->id,
                    'amount_cents' => (int) round((float) $installment->balance * 100),
                ])
                ->filter(fn(array $allocation) => $allocation['amount_cents'] > 0)
                ->values();

            $amountCents = $allocations->sum('amount_cents');

            if ($amountCents < 100) {
                throw ValidationException::withMessages([
                    'payment' => 'There is no Pay Later balance currently due.',
                ]);
            }

            // PayMongo's GCash and Maya transaction limit is PHP 100,000.
            if ($validated['method'] !== 'qrph' && $amountCents > 10_000_000) {
                throw ValidationException::withMessages([
                    'payment' => 'This amount exceeds the GCash/Maya limit. Choose QR Ph or contact the store.',
                ]);
            }

            $attempt = $user->creditPaymentAttempts()->create([
                'reference' => (string) Str::uuid(),
                'method' => $validated['method'],
                'amount_cents' => $amountCents,
                'currency' => 'PHP',
                'status' => 'creating',
            ]);

            $attempt->allocations()->createMany($allocations->all());

            return $attempt;
        });

        try {
            $response = Http::withBasicAuth($secretKey, '')
                ->acceptJson()
                ->timeout(20)
                ->post('https://api.paymongo.com/v1/payment_intents', [
                    'data' => [
                        'attributes' => [
                            'amount' => $attempt->amount_cents,
                            'currency' => 'PHP',
                            'payment_method_allowed' => [$attempt->method],
                            'description' => 'Pay Later payment ' . $attempt->reference,
                        ],
                    ],
                ]);

            if (!$response->successful()) {
                $attempt->update(['status' => 'failed']);

                return response()->json([
                    'message' => 'PayMongo could not start this payment. Please try again.',
                ], 502);
            }

            $intent = $response->json('data');
            $intentId = data_get($intent, 'id');
            $clientKey = data_get($intent, 'attributes.client_key');

            if (blank($intentId) || blank($clientKey)) {
                $attempt->update(['status' => 'failed']);

                return response()->json([
                    'message' => 'PayMongo returned an incomplete payment response.',
                ], 502);
            }

            $attempt->update([
                'paymongo_payment_intent_id' => $intentId,
                'status' => 'pending',
            ]);

            return response()->json([
                'reference' => $attempt->reference,
                'payment_intent_id' => $intentId,
                'client_key' => $clientKey,
                'public_key' => config('services.paymongo.public'),
                'amount_cents' => $attempt->amount_cents,
                'method' => $attempt->method,
            ], 201);
        } catch (Throwable $exception) {
            $attempt->update(['status' => 'failed']);
            report($exception);

            return response()->json([
                'message' => 'Could not reach PayMongo. Please try again.',
            ], 502);
        }
    }
    public function show(Request $request, string $reference)
    {
        $attempt = $request->user()
            ->creditPaymentAttempts()
            ->where('reference', $reference)
            ->firstOrFail();

        $attributes = [];
        $providerStatus = $attempt->status;

        if ($attempt->status === 'pending') {
            $secretKey = config('services.paymongo.secret');

            if (blank($secretKey) || blank($attempt->paymongo_payment_intent_id)) {
                abort(503, 'Payment details are unavailable.');
            }

            $response = Http::withBasicAuth($secretKey, '')
                ->acceptJson()
                ->timeout(20)
                ->get(
                    'https://api.paymongo.com/v1/payment_intents/'
                        . rawurlencode($attempt->paymongo_payment_intent_id)
                );

            if (!$response->successful()) {
                abort(502, 'Could not retrieve the payment from PayMongo.');
            }

            $intent = $response->json('data');
            $attributes = data_get($intent, 'attributes', []);

            $matchesAttempt =
                data_get($intent, 'id') === $attempt->paymongo_payment_intent_id
                && (int) data_get($attributes, 'amount', -1) === $attempt->amount_cents
                && strtoupper((string) data_get($attributes, 'currency')) === $attempt->currency;

            if (!$matchesAttempt) {
                $attempt->update(['status' => 'review']);
                $providerStatus = 'review';
            } else {
                $providerStatus = data_get($attributes, 'status', 'pending');

                if (in_array($providerStatus, ['awaiting_payment_method', 'cancelled'], true)) {
                    $attempt->update(['status' => 'failed']);
                    $providerStatus = 'failed';
                } elseif (
                    in_array($providerStatus, ['awaiting_next_action', 'processing'], true)
                    && !$attempt->expires_at
                ) {
                    $minutes = match ($attempt->method) {
                        'gcash' => 240,
                        'qrph', 'paymaya' => 30,
                        default => 30,
                    };

                    $updatedAt = data_get($attributes, 'updated_at');

                    $startedAt = is_numeric($updatedAt)
                        ? Carbon::createFromTimestamp((int) $updatedAt)
                        : now();

                    $attempt->expires_at = $startedAt->copy()->addMinutes($minutes);
                    $attempt->save();
                }
            }
        }

        return view('customer.paylater.payment', [
            'attempt' => $attempt,
            'providerStatus' => $providerStatus,
            'attributes' => $attributes,
            'expiresAt' => $attempt->expires_at,
        ]);
    }

    public function status(Request $request, string $reference)
    {
        $attempt = $request->user()
            ->creditPaymentAttempts()
            ->where('reference', $reference)
            ->firstOrFail();

        if ($attempt->status === 'succeeded') {
            return response()->json(['status' => 'succeeded']);
        }

        if ($attempt->status === 'review') {
            return response()->json(['status' => 'review'], 409);
        }

        if ($attempt->status !== 'pending' || blank($attempt->paymongo_payment_intent_id)) {
            return response()->json(['status' => $attempt->status], 409);
        }

        $secretKey = config('services.paymongo.secret');

        if (blank($secretKey)) {
            return response()->json(['message' => 'PayMongo is not configured.'], 503);
        }

        $response = Http::withBasicAuth($secretKey, '')
            ->acceptJson()
            ->timeout(20)
            ->get(
                'https://api.paymongo.com/v1/payment_intents/'
                    . rawurlencode($attempt->paymongo_payment_intent_id)
            );

        if (!$response->successful()) {
            return response()->json([
                'message' => 'Could not check the payment with PayMongo.',
            ], 502);
        }

        $intent = $response->json('data');
        $attributes = data_get($intent, 'attributes', []);

        $matchesAttempt =
            data_get($intent, 'id') === $attempt->paymongo_payment_intent_id
            && (int) data_get($attributes, 'amount', -1) === $attempt->amount_cents
            && strtoupper((string) data_get($attributes, 'currency')) === $attempt->currency;

        if (!$matchesAttempt) {
            $attempt->update(['status' => 'review']);

            return response()->json(['status' => 'review'], 409);
        }

        $providerStatus = data_get($attributes, 'status');

        if ($providerStatus === 'awaiting_payment_method' || $providerStatus === 'cancelled') {
            $attempt->update(['status' => 'failed']);

            return response()->json(['status' => 'failed']);
        }

        if (
            $providerStatus === 'awaiting_next_action'
            && $attempt->expires_at
            && now()->greaterThanOrEqualTo($attempt->expires_at->copy()->addMinutes(2))
        ) {
            $attempt->update(['status' => 'failed']);

            return response()->json(['status' => 'failed']);
        }

        if ($providerStatus !== 'succeeded') {
            return response()->json(['status' => 'pending']);
        }

        $result = DB::transaction(function () use ($request, $attempt) {
            $user = User::query()
                ->lockForUpdate()
                ->findOrFail($request->user()->id);

            $lockedAttempt = $user->creditPaymentAttempts()
                ->whereKey($attempt->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedAttempt->status === 'succeeded') {
                return 'succeeded';
            }

            if ($lockedAttempt->status !== 'pending') {
                return $lockedAttempt->status;
            }

            $allocations = $lockedAttempt->allocations()
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $allocationTotal = (int) $allocations->sum('amount_cents');

            if ($allocationTotal !== $lockedAttempt->amount_cents || $allocations->isEmpty()) {
                $lockedAttempt->update(['status' => 'review']);

                return 'review';
            }

            // Validate every installment before writing any ledger records.
            $paymentsToRecord = [];

            foreach ($allocations as $allocation) {
                $installment = CreditInstallment::query()
                    ->lockForUpdate()
                    ->findOrFail($allocation->credit_installment_id);

                $order = Order::query()
                    ->lockForUpdate()
                    ->findOrFail($installment->order_id);

                $balanceCents = (int) round((float) $installment->balance * 100);

                if (
                    $order->user_id !== $user->id
                    || $order->payment_method !== 'paylater'
                    || $order->order_status === 'cancelled'
                    || $balanceCents !== $allocation->amount_cents
                ) {
                    $lockedAttempt->update(['status' => 'review']);

                    return 'review';
                }

                $paymentsToRecord[] = [$installment, $order, $allocation];
            }

            foreach ($paymentsToRecord as [$installment, $order, $allocation]) {
                CreditPayment::create([
                    'order_id' => $order->id,
                    'credit_installment_id' => $installment->id,
                    'amount' => $allocation->amount_cents / 100,
                    'paid_at' => now(),
                    'note' => 'PayMongo payment ' . $lockedAttempt->reference,
                ]);
            }

            $lockedAttempt->update([
                'status' => 'succeeded',
                'paid_at' => now(),
            ]);

            $user->refreshCreditHold();

            return 'succeeded';
        });

        if ($result === 'review') {
            return response()->json(['status' => 'review'], 409);
        }

        return response()->json(['status' => $result]);
    }
}
