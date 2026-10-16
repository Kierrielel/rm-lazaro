@extends('layouts.customer')

@section('title', 'Your Cart')

@section('content')
<main class="cart-main-container">
    <div class="cart-card-align">
        <div class="cart-list">
            @forelse ($items as $item)
                <div class="cart-list-container">
                    <div class="cart-list-img">
                        <img src="{{ ($item->variant ?? $item->product)->image_url }}" alt="{{ $item->product->name }}">
                    </div>
                    <div class="cart-list-name">
                        <a href="{{ route('products.show', $item->product) }}">{{ $item->product->name }}</a>
                        @if ($item->variant) <small>{{ $item->variant->name }}</small> @endif
                    </div>
                    <div class="cart-list-price">
                        <p>&#8369;{{ number_format($item->unit_price, 2) }}</p>
                    </div>
                    <div class="button">
                        <div class="add-button">
                            <form action="{{ route('cart.update', $item) }}" method="POST" style="display:inline">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="action" value="decrease">
                                <button type="submit" class="qty-btn minus-icon">-</button>
                            </form>
                            <div class="add-text">{{ $item->quantity }}</div>
                            <form action="{{ route('cart.update', $item) }}" method="POST" style="display:inline">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="action" value="increase">
                                <button type="submit" class="qty-btn add-icon">+</button>
                            </form>
                        </div>
                    </div>
                    <div class="cart-list-subtotal">
                        <p>&#8369;{{ number_format($item->subtotal, 2) }}</p>
                    </div>
                    <form action="{{ route('cart.remove', $item) }}" method="POST">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="remove-btn">Remove</button>
                    </form>
                </div>
            @empty
                <p>Your cart is empty.</p>
            @endforelse
        </div>

        @if ($items->isNotEmpty())
            <div class="cart-summary">
                <p>Total: &#8369;{{ number_format($total, 2) }}</p>
                <form action="{{ route('checkout.store') }}" method="POST">
                    @csrf
                    <button type="submit" class="checkout-btn">Checkout</button>
                </form>
            </div>
        @endif
    </div>
</main>
@endsection
