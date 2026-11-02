<?php

namespace App\Http\Controllers;

use App\Models\CreditPaymentAttempt;
use App\PayLaterPayments;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class PayLaterPaymentController extends Controller
{
    public function store(Request $request, PayLaterPayments $payments): RedirectResponse
    {
        $request->validate(['method' => ['required', 'in:qrph']]);
        try {
            $attempt = $payments->start($request->user());
        } catch (ValidationException|HttpException $exception) {
            throw $exception;
        } catch (Throwable) {
            return redirect()->route('credit.apply')->withErrors([
                'payment' => 'Could not open PayMongo checkout. Check your saved payment below before trying again.',
            ]);
        }

        return $this->checkoutRedirect($attempt, $payments);
    }

    public function resume(Request $request, string $reference, PayLaterPayments $payments): RedirectResponse
    {
        $attempt = $this->ownedAttempt($request, $reference);
        try {
            $attempt = $payments->sync($attempt);
        } catch (Throwable) {
            return redirect()->route('paylater.payment-attempts.show', $reference)
                ->withErrors(['payment' => 'PayMongo is unavailable. Please check again shortly.']);
        }

        return $this->checkoutRedirect($attempt, $payments);
    }

    public function show(Request $request, string $reference, PayLaterPayments $payments): Response
    {
        $attempt = $this->ownedAttempt($request, $reference);
        $verificationError = null;
        try {
            $attempt = $payments->sync($attempt);
        } catch (Throwable) {
            $verificationError = 'Could not reach PayMongo. Your payment has not been confirmed. Check again shortly.';
        }

        return response()->view('customer.paylater.payment', compact('attempt', 'verificationError'))
            ->header('Cache-Control', 'private, no-store');
    }

    public function status(Request $request, string $reference, PayLaterPayments $payments): JsonResponse
    {
        $attempt = $this->ownedAttempt($request, $reference);
        try {
            $attempt = $payments->sync($attempt);
        } catch (Throwable) {
            return response()->json(['message' => 'Could not verify payment. Please check again.'], 502)
                ->header('Cache-Control', 'private, no-store');
        }

        return response()->json(['status' => $attempt->status])->header('Cache-Control', 'private, no-store');
    }

    private function ownedAttempt(Request $request, string $reference): CreditPaymentAttempt
    {
        return $request->user()->creditPaymentAttempts()->where('reference', $reference)->firstOrFail();
    }

    private function checkoutRedirect(CreditPaymentAttempt $attempt, PayLaterPayments $payments): RedirectResponse
    {
        if ($attempt->status === 'pending' && $attempt->paymongo_checkout_session_id && $payments->validCheckoutUrl($attempt->checkout_url)) {
            return redirect()->away($attempt->checkout_url, 303);
        }

        return redirect()->route('paylater.payment-attempts.show', $attempt->reference, 303);
    }
}
