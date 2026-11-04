<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Product;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CartController extends Controller
{
    public function index(Request $request): View
    {
        $items = Cart::with(['product', 'variant'])->where('user_id', $request->user()->id)->get();
        $total = $items->sum(fn ($item) => $item->subtotal);

        return view('customer.cart', compact('items', 'total'));
    }

    public function add(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'variant_id' => ['nullable', 'integer'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        return DB::transaction(function () use ($request, $data): RedirectResponse {
            $userId = $request->user()->id;
            User::query()->lockForUpdate()->findOrFail($userId);
            $product = Product::with('variants')->findOrFail($data['product_id']);

            $variant = null;
            if ($product->hasVariants()) {
                $variant = $product->variants->firstWhere('id', $data['variant_id'] ?? null);

                if (! $variant) {
                    return back()->withErrors(['variant_id' => 'Please choose all options.']);
                }
                $available = $variant->stock_quantity;
            } else {
                $available = $product->stock_quantity;
            }

            $existing = Cart::where('user_id', $userId)
                ->where('product_id', $product->id)
                ->where('variant_id', $variant?->id)
                ->first();

            $newQuantity = $data['quantity'] + ($existing->quantity ?? 0);

            if ($newQuantity > $available) {
                return back()->withErrors(['quantity' => 'Not enough stock available.']);
            }

            if ($existing) {
                $existing->update(['quantity' => $newQuantity]);
            } else {
                Cart::create([
                    'user_id' => $userId,
                    'product_id' => $product->id,
                    'variant_id' => $variant?->id,
                    'quantity' => $data['quantity'],
                ]);
            }

            return back()->with('success', 'Added to cart.');
        }, 3);
    }

    public function update(Request $request, Cart $cart): RedirectResponse
    {
        abort_unless($cart->user_id === $request->user()->id, 403);

        $action = $request->validate(['action' => ['required', 'in:increase,decrease']])['action'];

        return DB::transaction(function () use ($request, $cart, $action): RedirectResponse {
            User::query()->lockForUpdate()->findOrFail($request->user()->id);
            $cart = Cart::query()->where('user_id', $request->user()->id)->lockForUpdate()->findOrFail($cart->id);
            $cart->load('product', 'variant');

            $available = $cart->variant ? $cart->variant->stock_quantity : $cart->product->stock_quantity;

            $newQuantity = $cart->quantity;
            if ($action === 'increase' && $newQuantity < $available) {
                $newQuantity++;
            } elseif ($action === 'decrease') {
                $newQuantity--;
            }

            if ($newQuantity <= 0) {
                $cart->delete();
            } else {
                $cart->update(['quantity' => $newQuantity]);
            }

            return back();
        }, 3);
    }

    public function remove(Request $request, Cart $cart): RedirectResponse
    {
        abort_unless($cart->user_id === $request->user()->id, 403);
        DB::transaction(function () use ($request, $cart): void {
            User::query()->lockForUpdate()->findOrFail($request->user()->id);
            Cart::query()->where('user_id', $request->user()->id)->whereKey($cart->id)->delete();
        }, 3);

        return back()->with('success', 'Item removed.');
    }
}
