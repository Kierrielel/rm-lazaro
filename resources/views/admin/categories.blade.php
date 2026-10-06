@extends('layouts.admin')

@section('title', 'Categories')

@section('content')
<h2>Categories</h2>

<form method="POST" action="{{ route('admin.categories.store') }}" class="category-form">
    @csrf
    <input type="text" name="name" placeholder="Category name" value="{{ old('name') }}" required>
    <input type="text" name="description" placeholder="Description" value="{{ old('description') }}">
    <button type="submit">Add Category</button>
</form>

<table>
    <thead>
        <tr>
            <th>Name</th>
            <th>Description</th>
            <th>Products</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($categories as $category)
            <tr>
                <td>{{ $category->name }}</td>
                <td>{{ $category->description }}</td>
                <td>{{ $category->products_count }}</td>
                <td>
                    <form action="{{ route('admin.categories.destroy', $category) }}" method="POST"
                          onsubmit="return confirm('Delete this category?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit">Delete</button>
                    </form>
                </td>
            </tr>
        @endforeach
    </tbody>
</table>
@endsection
