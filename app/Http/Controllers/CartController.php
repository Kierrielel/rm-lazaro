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
        $total = $items->sum(fn ($item) => $item->quantity * $item->product->price);

        return view('customer.cart', compact('items', 'total'));
    }

    public function add(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $product = Product::findOrFail($data['product_id']);
        $userId = $request->user()->id;

        $existing = Cart::where('user_id', $userId)->where('product_id', $product->id)->first();
        $newQuantity = $data['quantity'] + ($existing->quantity ?? 0);

        if ($newQuantity > $product->stock_quantity) {
            return back()->withErrors(['quantity' => 'Not enough stock available.']);
        }

        if ($existing) {
            $existing->update(['quantity' => $newQuantity]);
        } else {
            Cart::create(['user_id' => $userId, 'product_id' => $product->id, 'quantity' => $data['quantity']]);
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
