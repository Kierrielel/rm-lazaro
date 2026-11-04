<?php

namespace App\Http\Controllers;

use App\Checkout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    public function store(Request $request, Checkout $checkout): RedirectResponse
    {
        $data = $request->validate([
            'payment_method' => ['required', 'in:cod,paylater'],
            'credit_term_months' => ['exclude_unless:payment_method,paylater', 'required', 'integer', 'in:3,6,9,12'],
            'confirm_credit_plan' => ['exclude_unless:payment_method,paylater', 'accepted'],
        ], [
            'payment_method.in' => 'This payment method is currently unavailable for cart checkout.',
        ]);

        try {
            $order = $checkout->place($request->user(), $data['payment_method'], isset($data['credit_term_months']) ? (int) $data['credit_term_months'] : null);
        } catch (ValidationException $exception) {
            throw $exception->redirectTo(route('cart.index'));
        }

        return $order
            ? redirect()->route('cart.index')->with('success', 'Order placed.')
            : redirect()->route('cart.index');
    }
}
