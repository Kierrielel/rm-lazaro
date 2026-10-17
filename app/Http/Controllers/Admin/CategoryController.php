<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::withCount('products')->orderBy('name')->get();
        return view('admin.categories', compact('categories'));
    }

    public function store(Request $request)
    {
        $data = $this->validateCategory($request);

        unset($data['image']);

        if ($request->hasFile('image')) {
            $data['image_path'] = $this->storeImage($request);
        }

        Category::create($data);

        return redirect()->route('admin.categories')->with('success', 'Category added.');
    }

    public function edit(Category $category)
    {
        return view('admin.edit_category', compact('category'));
    }

    public function update(Request $request, Category $category)
    {

        $validated = $this->validateCategory($request, $category->id);

        unset($validated['image']);

        if ($request->hasFile('image')) {
            $validated['image_path'] = $this->storeImage($request);
        }

        $category->update($validated);

        return redirect()->route('admin.categories')->with('success', 'Category updated.');
    }

    private function validateCategory(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:categories,name' . ($ignoreId ? ",{$ignoreId}" : '')],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'image', 'max:4096'],
        ]);
    }

    private function storeImage(Request $request): string
    {
        $file = $request->file('image');
        $filename = time() . '_' . $file->getClientOriginalName();
        $file->move(public_path('category-images'), $filename);

        return 'category-images/' . $filename;
    }

    public function destroy(Category $category)
    {
        if ($category->products()->exists()) {
            return back()->withErrors(['category' => 'Cannot delete a category that still has products.']);
        }

        $category->delete();
        return redirect()->route('admin.categories')->with('success', 'Category deleted.');
    }
}
