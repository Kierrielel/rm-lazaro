@extends('layouts.customer')

@section('title', 'Products')

@section('content')
<div class="products-page">
    <aside class="category-filter">
        <h4>Categories</h4>
        <a href="{{ route('products.index') }}" class="{{ !request('category') ? 'active' : '' }}">All</a>
        @foreach ($categories as $category)
            <a href="{{ route('products.index', ['category' => $category->id]) }}"
               class="{{ request('category') == $category->id ? 'active' : '' }}">
                {{ $category->name }}
            </a>
        @endforeach
    </aside>

    <section class="products-grid">
        @forelse ($products as $product)
            <a href="{{ route('products.show', $product) }}" class="product-card">
                <img src="{{ $product->image_url }}" alt="{{ $product->name }}" loading="lazy">
                <h4>{{ $product->name }}</h4>
                <p class="brand">{{ $product->brand }}</p>
                <p class="price">&#8369;{{ number_format($product->price, 2) }}</p>
                @if ($product->stock_quantity < 1)
                    <span class="out-of-stock">Out of stock</span>
                @endif
            </a>
        @empty
            <p>No products found.</p>
        @endforelse
    </section>
</div>

<div class="pagination">
    {{ $products->links() }}
</div>
@endsection
