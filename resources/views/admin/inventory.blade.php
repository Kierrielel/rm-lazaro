@extends('layouts.admin')

@section('title', 'Inventory')

@section('content')
<div class="inventory-header">
    <h2>Inventory</h2>
    <a href="{{ route('admin.inventory.create') }}" class="btn-primary">+ Add Product</a>
</div>

<form method="GET" action="{{ route('admin.inventory') }}" class="search-form">
    <input type="search" name="search" placeholder="Search products" value="{{ request('search') }}">
    <button type="submit">Search</button>
</form>

<table>
    <thead>
        <tr>
            <th>Image</th>
            <th>Name</th>
            <th>Category</th>
            <th>Price</th>
            <th>Stock</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($products as $product)
            <tr>
                <td><img src="{{ $product->image_url }}" alt="{{ $product->name }}" width="48"></td>
                <td>{{ $product->name }}</td>
                <td>{{ $product->category->name ?? '—' }}</td>
                <td>&#8369;{{ number_format($product->price, 2) }}</td>
                <td>{{ $product->stock_quantity }}</td>
                <td>
                    <a href="{{ route('admin.inventory.edit', $product) }}">Edit</a>
                    <form action="{{ route('admin.inventory.destroy', $product) }}" method="POST" style="display:inline"
                          onsubmit="return confirm('Delete this product?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit">Delete</button>
                    </form>
                </td>
            </tr>
        @endforeach
    </tbody>
</table>

{{ $products->links() }}
@endsection
