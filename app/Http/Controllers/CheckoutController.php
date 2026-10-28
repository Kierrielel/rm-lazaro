<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CheckoutController extends Controller
{
    public function store(Request $request)
    {
        $userId = $request->user()->id;

        $cartItems = Cart::with(['product', 'variant'])
            ->where('user_id', $userId)
            ->get();

        if ($cartItems->isEmpty()) {
            return redirect()->route('cart.index');
        }

        foreach ($cartItems as $item) {
            $label = $item->product->name
                . ($item->variant ? " ({$item->variant->name})" : '');

            if (!$item->variant && $item->product->hasVariants()) {
                return redirect()->route('cart.index')
                    ->withErrors([
                        'stock' => "{$label} now has options. Remove it and add it again.",
                    ]);
            }

            $available = $item->variant
                ? $item->variant->stock_quantity
                : $item->product->stock_quantity;

            if ($item->quantity > $available) {
                return redirect()->route('cart.index')
                    ->withErrors([
                        'stock' => "{$label} no longer has enough stock.",
                    ]);
            }
        }

        $principal = round((float) $cartItems->sum(fn($item) => $item->subtotal), 2);

        $validated = $request->validate([
            'payment_method' => ['required', 'in:cod,qrph,gcash,maya,paylater'],
            'credit_term_months' => [
                'exclude_unless:payment_method,paylater',
                'required',
                'integer',
                'in:3,6,9,12',
            ],
            'confirm_credit_plan' => [
                'exclude_unless:payment_method,paylater',
                'accepted',
            ],
        ]);

        $paymentMethod = $validated['payment_method'];
        $termMonths = null;
        $interestRate = null;
        $financeCharge = 0;
        $payableTotal = $principal;

        if ($paymentMethod === 'qrph' && $principal >= 3000) {
            return redirect()->route('cart.index')
                ->withInput()
                ->withErrors([
                    'payment_method' => 'QR Ph is available only for orders below ₱3,000.',
                ]);
        }

        if ($paymentMethod === 'paylater') {
            $termMonths = (int) $validated['credit_term_months'];
            $interestRate = 3.5;

            $financeCharge = round($principal * ($interestRate / 100) * $termMonths, 2);
            $payableTotal = round($principal + $financeCharge, 2);
        }

        $orderDate = now();

        $orderPlaced = DB::transaction(function () use (
            $cartItems,
            $userId,
            $principal,
            $paymentMethod,
            $termMonths,
            $interestRate,
            $financeCharge,
            $orderDate,
            $payableTotal,
        ) {
            $firstDueDate = null;
            $dueDay = null;

            if ($paymentMethod === 'paylater') {
                $lockedUser = User::query()
                    ->lockForUpdate()
                    ->findOrFail($userId);

                $lockedUser->refreshCreditHold();

                if (!$lockedUser->canUseCredit($payableTotal)) {
                    return false;
                }

                $dueDay = $lockedUser->credit_due_day;

                if (!in_array($dueDay, ['15', 'last_day'], true)) {
                    return false;
                }

                $candidateDate = $orderDate->copy()->startOfDay();

                if ($dueDay === '15') {
                    $firstDueDate = $candidateDate->day < 15
                        ? $candidateDate->day(15)
                        : $candidateDate->addMonthNoOverflow()->day(15);
                } else {
                    $firstDueDate = $candidateDate->day < $candidateDate->daysInMonth
                        ? $candidateDate->endOfMonth()
                        : $candidateDate->addMonthNoOverflow()->endOfMonth();
                }
            }
            $order = Order::create([
                'user_id' => $userId,
                'total_amount' => $principal,
                'order_status' => 'pending',
                'order_date' => $orderDate,
                'payment_method' => $paymentMethod,
                'due_date' => $firstDueDate?->toDateString(),
                'credit_term_months' => $termMonths,
                'credit_interest_rate' => $interestRate,
                'credit_finance_charge' => $financeCharge,
            ]);

            if ($paymentMethod === 'paylater') {
                $payableTotal = round($principal + $financeCharge, 2);
                $totalCents = (int) round($payableTotal * 100);
                $term = $termMonths;
                $baseCents = intdiv($totalCents, $term);
                $extraCents = $totalCents % $term;

                for ($number = 1; $number <= $term; $number++) {
                    $installmentCents = $baseCents + ($number <= $extraCents ? 1 : 0);

                    $installmentDate = $firstDueDate->copy()
                        ->addMonthsNoOverflow($number - 1);

                    if ($dueDay === '15') {
                        $installmentDate->day(15);
                    } else {
                        $installmentDate->endOfMonth();
                    }

                    $order->installments()->create([
                        'installment_number' => $number,
                        'due_date' => $installmentDate->toDateString(),
                        'amount_due' => $installmentCents / 100,
                    ]);
                }
            }

            foreach ($cartItems as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item->product_id,
                    'variant_id' => $item->variant_id,
                    'variant_name' => $item->variant?->name,
                    'quantity' => $item->quantity,
                    'price' => $item->unit_price,
                ]);

                if ($item->variant) {
                    $item->variant->decrement('stock_quantity', $item->quantity);
                } else {
                    $item->product->decrement('stock_quantity', $item->quantity);
                }
            }

            Cart::where('user_id', $userId)->delete();

            return true;
        });

        if (!$orderPlaced) {
            return redirect()->route('cart.index')->withErrors([
                'payment_method' => 'You are not approved for this Pay Later amount, or it exceeds your available credit.',
            ]);
        }

        return redirect()->route('cart.index')->with('success', 'Order placed.');
    }
}
