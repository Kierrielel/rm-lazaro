<?php

namespace App\Http\Controllers;

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

        $cartItems = Cart::with(['product', 'variant'])->where('user_id', $userId)->get();

        if ($cartItems->isEmpty()) {
            return redirect()->route('cart.index');
        }

        foreach ($cartItems as $item) {
            $label = $item->product->name . ($item->variant ? " ({$item->variant->name})" : '');

            // the admin added options after this was put in the cart
            if (!$item->variant && $item->product->hasVariants()) {
                return redirect()->route('cart.index')
                    ->withErrors(['stock' => "{$label} now has options. Remove it and add it again."]);
            }

            $available = $item->variant
                ? $item->variant->stock_quantity
                : $item->product->stock_quantity;

            if ($item->quantity > $available) {
                return redirect()->route('cart.index')
                    ->withErrors(['stock' => "{$label} no longer has enough stock."]);
            }
        }

        $total = $cartItems->sum(fn ($item) => $item->subtotal);

        DB::transaction(function () use ($cartItems, $userId, $total) {
            $order = Order::create([
                'user_id' => $userId,
                'total_amount' => $total,
                'order_status' => 'pending',
                'order_date' => now(),
            ]);

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
        });

        return redirect()->route('cart.index')->with('success', 'Order placed.');
    }
}