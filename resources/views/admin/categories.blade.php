@extends('layouts.admin')

@section('title', 'Categories')

@section('content')
<div class="categories-page">
    <h2>Categories</h2>
    <p class="page-subtitle">Organize your products into categories.</p>


    <form method="POST" action="{{ route('admin.categories.store') }}" class="category-form">
        @csrf
        <h3 class="form-title">Add New Category</h3>
        <input type="text" name="name" placeholder="Category name" value="{{ old('name') }}" required>
        <input type="text" name="description" placeholder="Description" value="{{ old('description') }}">
        <button type="submit">Add Category</button>
    </form>


    <div class="table-card">
        <h3>All Categories</h3>
        <table class="dashboard-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Description</th>
                    <th>Products</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($categories as $category)
                    <tr>
                        <td>
                            <div class="category-name">
                                <span class="category-letter">{{ strtoupper(mb_substr($category->name, 0, 1)) }}</span>
                                {{ $category->name }}
                            </div>
                        </td>
                        <td class="category-description">{{ $category->description }}</td>
                        <td><span class="count-badge">{{ $category->products_count }}</span></td>
                        <td>
                            <form action="{{ route('admin.categories.destroy', $category) }}" method="POST"
                                  onsubmit="return confirm('Delete this category?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="delete-btn">Delete</button>
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