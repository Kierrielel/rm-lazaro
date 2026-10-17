@extends('layouts.admin')

@section('title', 'Categories')

@section('content')
<link rel="stylesheet" href="{{ asset('css/inventory.css') }}">
<link rel="stylesheet" href="{{ asset('css/categories.css') }}">

<div class="categories-page">

    <div class="categories-header">
        <h2>Categories</h2>
        <p class="subtitle">Organize your products into categories.</p>
    </div>

    <form method="POST" action="{{ route('admin.categories.store') }}" class="category-form" enctype="multipart/form-data">
        @csrf
        <h3 class="form-title">Add New Category</h3>

        <div class="category-fields">

            <input type="text" name="name" placeholder="Category name" value="{{ old('name') }}" required>
            <input type="text" name="description" placeholder="Description (optional)" value="{{ old('description') }}">
            <input type="file" name="image" id="image" accept="image/*">
            <button type="submit" class="btn btn-primary">Add Category</button>
        </div>
    </form>

    <div class="table-card">
        <div class="table-card-header">
            <h3>All Categories</h3>
        </div>

        <table class="category-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Description</th>
                    <th>Products</th>
                    <th class="actions-col">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($categories as $category)
                    <tr>
                        <td data-label="Name">
                            <div class="category-name">
                                <span class="category-letter">{{ strtoupper(mb_substr($category->name, 0, 1)) }}</span>
                                <span>{{ $category->name }}</span>
                            </div>
                        </td>

                        <td data-label="Description" class="category-description">
                            {{ $category->description ?: '—' }}
                        </td>

                        <td data-label="Products">
                            <span class="count-badge">{{ $category->products_count }}</span>
                        </td>

                        <td data-label="Actions" class="actions-col">
                            <a href="{{ route('admin.categories.edit', $category) }}" class="btn btn-light">Edit</a>

                            <form action="{{ route('admin.categories.destroy', $category) }}" method="POST"
                                  onsubmit="return confirm('Delete this category?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="empty-row">No categories yet. Add your first one above.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

</div>
@endsection