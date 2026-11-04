<?php

namespace App;

use App\Models\CreditPaymentAttemptAllocation;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderStatusUpdater
{
    public function update(Order $order, string $status): void
    {
        DB::transaction(function () use ($order, $status): void {
            User::query()->whereKey($order->user_id)->lockForUpdate()->first();
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
            if (! in_array($status, $locked->availableStatuses(), true)) {
                throw ValidationException::withMessages(['order_status' => 'This order cannot move to the selected status. Cancelled and completed orders are final.']);
            }
            if ($locked->order_status === $status) {
                return;
            }
            if ($status === 'cancelled') {
                $openPayment = CreditPaymentAttemptAllocation::query()
                    ->whereHas('installment', fn ($query) => $query->where('order_id', $locked->id))
                    ->whereHas('attempt', fn ($query) => $query->whereIn('status', ['creating', 'pending', 'review']))
                    ->exists();
                if ($locked->payments()->exists() || $openPayment) {
                    throw ValidationException::withMessages(['order_status' => 'This order has a recorded or unresolved payment. Resolve the payment before cancellation.']);
                }

                $items = $locked->items()->get();
                $products = Product::query()->whereIn('id', $items->pluck('product_id'))
                    ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                $variants = ProductVariant::query()->whereIn('id', $items->pluck('variant_id')->filter())
                    ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                foreach ($items as $item) {
                    if ($item->variant_id) {
                        $variant = $variants->get($item->variant_id);
                        if ($variant && $variant->product_id === $item->product_id) {
                            $variant->increment('stock_quantity', $item->quantity);
                        }
                    } elseif ($item->variant_name === null) {
                        $products->get($item->product_id)?->increment('stock_quantity', $item->quantity);
                    }
                }
            }
            $locked->update(['order_status' => $status]);
        }, 3);
    }
}
