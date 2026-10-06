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

        $cartItems = Cart::with('product')->where('user_id', $userId)->get();

        if ($cartItems->isEmpty()) {
            return redirect()->route('cart.index');
        }

        foreach ($cartItems as $item) {
            if ($item->quantity > $item->product->stock_quantity) {
                return redirect()->route('cart.index')
                    ->withErrors(['stock' => "{$item->product->name} no longer has enough stock."]);
            }
        }

        $total = $cartItems->sum(fn ($item) => $item->quantity * $item->product->price);

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
                    'quantity' => $item->quantity,
                    'price' => $item->product->price,
                ]);

                $item->product->decrement('stock_quantity', $item->quantity);
            }

            Cart::where('user_id', $userId)->delete();
        });

        return redirect()->route('cart.index')->with('success', 'Order placed.');
    }
}
