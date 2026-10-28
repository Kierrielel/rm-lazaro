@extends('layouts.admin')

@section('title', 'Inventory')

@section('content')
<link rel="stylesheet" href="{{ asset('css/inventory.css') }}">

<div class="inventory-page">

   
    <div class="inventory-header">
        <div>
            <h2>Inventory</h2>
            <p class="subtitle">Manage your products and stock levels.</p>
        </div>
        <a href="{{ route('admin.inventory.create') }}" class="btn btn-primary">+ Add Product</a>
    </div>

    
    <form method="GET" action="{{ route('admin.inventory') }}" class="search-form">
        <input type="search" name="search" placeholder="Search products..." value="{{ request('search') }}">
        <select name="category" onchange="this.form.submit()">
            <option value="">All Categories</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected(request('category') == $category->id)>{{ $category->name }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn btn-dark">Search</button>
    </form>

   
    <div class="table-card">
        <table class="product-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Category</th>
                    <th>Price</th>
                    <th>Stock</th>
                    <th class="actions-col">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($products as $product)
                    <tr>
                        <td data-label="Product">
                            <div class="product-info">
                                <img src="{{ $product->image_url }}" alt="{{ $product->name }}">
                                <span class="product-name">{{ $product->name }}</span>
                            </div>
                        </td>

                        <td data-label="Category">
                            <span class="category-tag">{{ $product->category->name ?? '—' }}</span>
                        </td>

                        <td data-label="Price">&#8369;{{ number_format($product->price, 2) }}</td>

                        <td data-label="Stock">
                            @if ($product->stock_quantity <= 0)
                                <span class="stock stock-out">Out of stock</span>
                            @elseif ($product->stock_quantity <= 10)
                                <span class="stock stock-low">{{ $product->stock_quantity }} left</span>
                            @else
                                <span class="stock stock-ok">{{ $product->stock_quantity }} in stock</span>
                            @endif
                        </td>

                        <td data-label="Actions" class="actions-col">
                            <div class="action-buttons">
                                <a href="{{ route('admin.inventory.edit', $product) }}" class="btn btn-light">Edit</a>

                                <form action="{{ route('admin.inventory.destroy', $product) }}" method="POST"
                                      onsubmit="return confirm('Delete this product?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="empty-row">No products found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        {{ $products->links() }}
    </div>
</div>
@endsection