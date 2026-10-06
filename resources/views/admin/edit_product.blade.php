@extends('layouts.admin')

@section('title', $product ? 'Edit Product' : 'Add Product')

@section('content')
<h2>{{ $product ? 'Edit Product' : 'Add Product' }}</h2>

<form method="POST"
      action="{{ $product ? route('admin.inventory.update', $product) : route('admin.inventory.store') }}"
      enctype="multipart/form-data">
    @csrf
    @if ($product) @method('PUT') @endif

    <label for="name">Product name</label>
    <input type="text" name="name" id="name" value="{{ old('name', $product->name ?? '') }}" required>

    <label for="brand">Brand</label>
    <input type="text" name="brand" id="brand" value="{{ old('brand', $product->brand ?? '') }}">

    <label for="category_id">Category</label>
    <select name="category_id" id="category_id" required>
        <option value="">-- Select --</option>
        @foreach ($categories as $category)
            <option value="{{ $category->id }}"
                @selected(old('category_id', $product->category_id ?? null) == $category->id)>
                {{ $category->name }}
            </option>
        @endforeach
    </select>

    <label for="price">Price</label>
    <input type="number" step="0.01" name="price" id="price" value="{{ old('price', $product->price ?? '') }}" required>

    <label for="stock_quantity">Stock quantity</label>
    <input type="number" name="stock_quantity" id="stock_quantity" value="{{ old('stock_quantity', $product->stock_quantity ?? '') }}" required>

    <label for="description">Description</label>
    <textarea name="description" id="description" rows="5">{{ old('description', $product->description ?? '') }}</textarea>

    <label for="image">Product image</label>
    @if ($product && $product->image_path)
        <img src="{{ $product->image_url }}" alt="{{ $product->name }}" width="80">
    @endif
    <input type="file" name="image" id="image" accept="image/*">

    <button type="submit">{{ $product ? 'Save Changes' : 'Add Product' }}</button>
</form>
@endsection
