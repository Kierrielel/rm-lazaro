@extends('layouts.customer')

@section('title', 'Your Cart')

@section('content')
<div class="cart-page">
    <h1 class="cart-title">Your Cart</h1>

    @if ($items->isNotEmpty())
        <div class="cart-layout">

            <div class="cart-list">
                @foreach ($items as $item)
                    <div class="cart-item">
                        <div class="cart-item-img">
                            <img src="{{ ($item->variant ?? $item->product)->image_url }}" alt="{{ $item->product->name }}">
                        </div>

                        <div class="cart-item-info">
                            <a href="{{ route('products.show', $item->product) }}">{{ $item->product->name }}</a>
                            @if ($item->variant)
                                <small>{{ $item->variant->name }}</small>
                            @endif
                            <p class="cart-item-price">&#8369;{{ number_format($item->unit_price, 2) }}</p>
                        </div>

                        <div class="qty-box">
                            <form action="{{ route('cart.update', $item) }}" method="POST">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="action" value="decrease">
                                <button type="submit" class="qty-btn">-</button>
                            </form>

                            <span class="qty-number">{{ $item->quantity }}</span>

                            <form action="{{ route('cart.update', $item) }}" method="POST">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="action" value="increase">
                                <button type="submit" class="qty-btn">+</button>
                            </form>
                        </div>

                        <div class="cart-item-subtotal">
                            <p>&#8369;{{ number_format($item->subtotal, 2) }}</p>
                            <form action="{{ route('cart.remove', $item) }}" method="POST">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="remove-btn">Remove</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="cart-summary">
                <h3>Order Summary</h3>

                <div class="summary-row">
                    <span>Items</span>
                    <span>{{ $items->sum('quantity') }}</span>
                </div>

                <div class="summary-row total-row">
                    <span>Total</span>
                    <strong>&#8369;{{ number_format($total, 2) }}</strong>
                </div>

                <form action="{{ route('checkout.store') }}" method="POST">
                    @csrf
                    <button type="submit" class="checkout-btn">Checkout</button>
                </form>
            </div>

        </div>
    @else
        <div class="empty-cart">
            <h3>Your cart is empty</h3>
            <p>Looks like you haven't added anything yet.</p>
            <a href="{{ route('products.index') }}" class="shop-now-btn">Shop Now</a>
        </div>
    @endif
</div>
@endsection