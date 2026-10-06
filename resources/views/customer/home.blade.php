@extends('layouts.customer')

@section('title', 'Home')

@section('content')
<div class="banner-images">
    <img src="{{ asset('images/1banner.jpg') }}" alt="banner">
</div>

<section class="products-section">
    <h2>Featured Products</h2>
    <div class="products-grid">
        @foreach ($featured as $product)
            <a href="{{ route('products.show', $product) }}" class="product-card">
                <img src="{{ $product->image_url }}" alt="{{ $product->name }}">
                <h4>{{ $product->name }}</h4>
                <p>&#8369;{{ number_format($product->price, 2) }}</p>
            </a>
        @endforeach
    </div>
</section>
@endsection
