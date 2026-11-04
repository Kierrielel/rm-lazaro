<?php

namespace App;

use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Checkout
{
    public function place(User $customer, string $paymentMethod, ?int $months = null): ?Order
    {
        if (! in_array($paymentMethod, ['cod', 'paylater'], true)) {
            throw ValidationException::withMessages(['payment_method' => 'This payment method is currently unavailable for cart checkout.']);
        }

        $result = DB::transaction(function () use ($customer, $paymentMethod, $months): Order|ValidationException|null {
            $user = User::query()->lockForUpdate()->findOrFail($customer->id);
            abort_if($user->archived_at !== null, 403);
            $items = $user->cart()->orderBy('id')->lockForUpdate()->get();
            if ($items->isEmpty()) {
                return null;
            }

            $products = Product::query()->whereIn('id', $items->pluck('product_id'))
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $variants = ProductVariant::query()->whereIn('product_id', $products->keys())
                ->orderBy('id')->lockForUpdate()->get();
            $principalCents = 0;

            foreach ($items as $item) {
                $product = $products->get($item->product_id);
                $variant = $item->variant_id ? $variants->firstWhere('id', $item->variant_id) : null;
                if (! $product || $item->quantity < 1
                    || ($item->variant_id && (! $variant || $variant->product_id !== $product->id))
                    || (! $item->variant_id && $variants->contains('product_id', $product->id))) {
                    throw ValidationException::withMessages(['stock' => 'A cart item has changed. Remove it and add it again.']);
                }

                $item->setRelation('product', $product);
                $item->setRelation('variant', $variant);
                $variant?->setRelation('product', $product);
                $price = (string) $item->unit_price;
                if (! preg_match('/\A[0-9]+\.[0-9]{2}\z/', $price)) {
                    throw ValidationException::withMessages(['stock' => 'A cart item has an invalid price. Please contact the store.']);
                }
                [$whole, $fraction] = array_pad(explode('.', $price, 2), 2, '0');
                $priceCents = (int) $whole * 100 + (int) str_pad($fraction, 2, '0');
                if ($priceCents < 0 || ($priceCents > 0 && $item->quantity > intdiv(9999999999 - $principalCents, $priceCents))) {
                    throw ValidationException::withMessages(['stock' => 'The order total exceeds the supported amount.']);
                }
                $principalCents += $priceCents * $item->quantity;
            }

            $financeCents = 0;
            $firstDueDate = null;
            $orderDate = now();
            if ($paymentMethod === 'paylater') {
                if (! in_array($months, [3, 6, 9, 12], true)) {
                    throw ValidationException::withMessages(['credit_term_months' => 'Choose a supported installment term.']);
                }
                $financeCents = intdiv($principalCents * 35 * $months + 500, 1000);
                $user->refreshCreditHold();
                if (! $user->canUseCredit(($principalCents + $financeCents) / 100)
                    || ! in_array($user->credit_due_day, ['15', 'last_day'], true)) {
                    return ValidationException::withMessages(['payment_method' => 'You are not approved for this Pay Later amount, or it exceeds your available credit.']);
                }
                $firstDueDate = $this->firstDueDate($orderDate, $user->credit_due_day);
            }

            if ($principalCents < 0 || $principalCents + $financeCents > 9999999999) {
                throw ValidationException::withMessages(['stock' => 'The order total exceeds the supported amount.']);
            }
            $order = $user->orders()->create([
                'total_amount' => $this->amount($principalCents),
                'order_status' => 'pending', 'order_date' => $orderDate,
                'payment_method' => $paymentMethod, 'due_date' => $firstDueDate?->toDateString(),
                'credit_term_months' => $paymentMethod === 'paylater' ? $months : null,
                'credit_interest_rate' => $paymentMethod === 'paylater' ? '3.50' : null,
                'credit_finance_charge' => $this->amount($financeCents),
            ]);

            if ($firstDueDate) {
                $totalCents = $principalCents + $financeCents;
                for ($number = 1; $number <= $months; $number++) {
                    $date = $firstDueDate->copy()->addMonthsNoOverflow($number - 1);
                    $date = $user->credit_due_day === '15' ? $date->day(15) : $date->endOfMonth();
                    $cents = intdiv($totalCents, $months) + ($number <= $totalCents % $months ? 1 : 0);
                    $order->installments()->create([
                        'installment_number' => $number, 'due_date' => $date->toDateString(),
                        'amount_due' => $this->amount($cents),
                    ]);
                }
            }

            foreach ($items as $item) {
                $stock = $item->variant ?? $item->product;
                $reserved = $stock->newQuery()->whereKey($stock->id)
                    ->where('stock_quantity', '>=', $item->quantity)
                    ->decrement('stock_quantity', $item->quantity);
                if ($reserved !== 1) {
                    throw ValidationException::withMessages(['stock' => "{$item->product->name} no longer has enough stock."]);
                }
                $order->items()->create([
                    'product_id' => $item->product_id, 'variant_id' => $item->variant_id,
                    'variant_name' => $item->variant?->name, 'quantity' => $item->quantity,
                    'price' => $item->unit_price,
                ]);
            }
            Cart::query()->whereIn('id', $items->modelKeys())->delete();

            return $order;
        }, 3);

        if ($result instanceof ValidationException) {
            throw $result;
        }

        return $result;
    }

    private function firstDueDate(Carbon $orderDate, string $dueDay): Carbon
    {
        $date = $orderDate->copy()->startOfDay();
        if ($dueDay === '15') {
            return $date->day < 15 ? $date->day(15) : $date->addMonthNoOverflow()->day(15);
        }

        return $date->day < $date->daysInMonth ? $date->endOfMonth() : $date->addMonthNoOverflow()->endOfMonth();
    }

    private function amount(int $cents): string
    {
        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
