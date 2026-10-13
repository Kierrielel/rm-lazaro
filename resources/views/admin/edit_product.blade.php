@extends('layouts.admin')

@section('title', $product ? 'Edit Product' : 'Add Product')

@section('content')
<link rel="stylesheet" href="{{ asset('css/inventory.css') }}">
<link rel="stylesheet" href="{{ asset('css/product-form.css') }}">

<div class="form-page">

    <a href="{{ route('admin.inventory') }}" class="back-link">&larr; Back to inventory</a>

    <div class="form-header">
        <h2>{{ $product ? 'Edit Product' : 'Add Product' }}</h2>
        <p class="subtitle">
            {{ $product ? 'Update the details of this product.' : 'Fill in the details of your new product.' }}
        </p>
    </div>

    <form method="POST"
          action="{{ $product ? route('admin.inventory.update', $product) : route('admin.inventory.store') }}"
          enctype="multipart/form-data"
          class="form-card">
        @csrf
        @if ($product) @method('PUT') @endif

        <div class="form-row">
            <div class="form-group">
                <label for="name">Product name</label>
                <input type="text" name="name" id="name" value="{{ old('name', $product->name ?? '') }}" required>
            </div>

            <div class="form-group">
                <label for="brand">Brand</label>
                <input type="text" name="brand" id="brand" value="{{ old('brand', $product->brand ?? '') }}">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
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
            </div>

            <div class="form-group">
                <label for="price">Price (&#8369;)</label>
                <input type="number" step="0.01" name="price" id="price" value="{{ old('price', $product->price ?? '') }}" required>
            </div>

            <div class="form-group">
                <label for="stock_quantity">Stock quantity</label>
                <input type="number" name="stock_quantity" id="stock_quantity" value="{{ old('stock_quantity', $product->stock_quantity ?? '') }}" required>
            </div>
        </div>

        <div class="form-group">
            <label for="description">Description</label>
            <textarea name="description" id="description" rows="5">{{ old('description', $product->description ?? '') }}</textarea>
        </div>

        <div class="form-group">
            <label for="image">Product image</label>
            <div class="image-upload">
                @if ($product && $product->image_path)
                    <img src="{{ $product->image_url }}" alt="{{ $product->name }}" class="current-image">
                @endif
                <input type="file" name="image" id="image" accept="image/*">
            </div>
        </div>


        <div class="form-buttons">
            <a href="{{ route('admin.inventory') }}" class="btn btn-light">Cancel</a>
            <button type="submit" class="btn btn-primary">{{ $product ? 'Save Changes' : 'Add Product' }}</button>
        </div>
    </form>

</div>
@endsection