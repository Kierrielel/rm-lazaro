@extends('layouts.customer')

@section('title', $product->name)

@section('content')
<div class="product-page">
    <div class="product-image">
        <img src="{{ $product->image_url }}" alt="{{ $product->name }}">
    </div>

    <div class="product-details">
        <h2>{{ $product->name }}</h2>
        <p class="brand">{{ $product->brand }}</p>
        <p class="price">&#8369;{{ number_format($product->price, 2) }}</p>
        <p class="stock">{{ $product->stock_quantity }} in stock</p>
        <p class="description">{{ $product->description }}</p>

        @auth
            @if ($product->stock_quantity > 0)
                <form method="POST" action="{{ route('cart.add') }}">
                    @csrf
                    <input type="hidden" name="product_id" value="{{ $product->id }}">
                    <label for="quantity">Quantity</label>
                    <input type="number" name="quantity" id="quantity" value="1" min="1" max="{{ $product->stock_quantity }}">
                    <button type="submit">Add to Cart</button>
                </form>
            @else
                <p class="out-of-stock">Out of stock</p>
            @endif
        @else
            <p><a href="{{ route('login') }}">Log in</a> to add this to your cart.</p>
        @endauth
    </div>
</div>
@endsection
