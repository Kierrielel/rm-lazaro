<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Product;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function index(Request $request)
    {
        $items = Cart::with('product')->where('user_id', $request->user()->id)->get();
        $total = $items->sum(fn($item) => $item->quantity * $item->product->price);

        return view('customer.cart', compact('items', 'total'));
    }

    public function add(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'variant_id' => ['nullable', 'integer'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $product = Product::with('variants')->findOrFail($data['product_id']);
        $userId = $request->user()->id;

        $variant = null;
        if ($product->hasVariants()) {
            $variant = $product->variants->firstWhere('id', $data['variant_id'] ?? null);

            if (!$variant) {
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
    }

    public function update(Request $request, Cart $cart)
    {
        abort_unless($cart->user_id === $request->user()->id, 403);

        $action = $request->validate(['action' => ['required', 'in:increase,decrease']])['action'];
        $cart->load('product');

        $newQuantity = $cart->quantity;
        if ($action === 'increase' && $newQuantity < $cart->product->stock_quantity) {
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
    }

    public function remove(Request $request, Cart $cart)
    {
        abort_unless($cart->user_id === $request->user()->id, 403);
        $cart->delete();

        return back()->with('success', 'Item removed.');
    }
}
