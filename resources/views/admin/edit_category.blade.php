@extends('layouts.admin')

@section('title', 'Edit Categories')

@section('content')
<link rel="stylesheet" href="{{ asset('css/inventory.css') }}">
<link rel="stylesheet" href="{{ asset('css/categories.css') }}">

<div class="form-page">

    <a href="{{ route('admin.categories') }}" class="back-link">&larr; Back to categories</a>

    <div class="form-header">
        <h2>Edit Category</h2>
        <p class="subtitle">Update the details of this category.</p>
    </div>

    <form method="POST"
          action="{{ route('admin.categories.update', $category) }}"
          enctype="multipart/form-data"
          class="form-card">
        @csrf
        @method('PUT')

        <div class="form-row">
            <div class="form-group">
                <label for="name">Category name</label>
                <input type="text" name="name" id="name"
                       value="{{ old('name', $category->name ?? '') }}" required>
                @error('name') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div class="form-group">
                <label for="description">Description</label>
                <textarea name="description" id="description" rows="5">{{ old('description', $category->description ?? '') }}</textarea>
                @error('description') <span class="field-error">{{ $message }}</span> @enderror
            </div>
        </div>

        <div class="form-group">
            <label for="image">Category image</label>
            <div class="image-upload">
                @if ($category && $category->image_path)
                    <img src="{{ $category->image_url }}" alt="{{ $category->name }}" class="current-image">
                @endif
                <input type="file" name="image" id="image" accept="image/*">
            </div>
            @error('image') <span class="field-error">{{ $message }}</span> @enderror
        </div>

        <div class="form-buttons">
            <a href="{{ route('admin.categories') }}" class="btn btn-light">Cancel</a>
            <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>
    </form>

</div>
@endsection