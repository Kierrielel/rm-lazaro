<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with('category');

        if ($search = $request->get('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        $products = $query->orderBy('name')->paginate(15)->withQueryString();

        return view('admin.inventory', compact('products'));
    }

    public function create()
    {
        $categories = Category::orderBy('name')->get();
        return view('admin.edit_product', ['product' => null, 'categories' => $categories]);
    }

    public function store(Request $request)
    {
        $data = $this->validateProduct($request);

        if ($request->hasFile('image')) {
            $data['image_path'] = $this->storeImage($request);
        }

        Product::create($data);

        return redirect()->route('admin.inventory')->with('success', 'Product added.');
    }

    public function edit(Product $product)
    {
        $categories = Category::orderBy('name')->get();
        return view('admin.edit_product', compact('product', 'categories'));
    }

    public function update(Request $request, Product $product)
    {
        $data = $this->validateProduct($request, $product->id);

        if ($request->hasFile('image')) {
            $data['image_path'] = $this->storeImage($request);
        }

        $product->update($data);

        return redirect()->route('admin.inventory')->with('success', 'Product updated.');
    }

    public function destroy(Product $product)
    {
        $product->delete();
        return redirect()->route('admin.inventory')->with('success', 'Product deleted.');
    }

    private function validateProduct(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:200', 'unique:products,name' . ($ignoreId ? ",{$ignoreId}" : '')],
            'brand' => ['nullable', 'string', 'max:100'],
            'category_id' => ['required', 'exists:categories,id'],
            'price' => ['required', 'numeric', 'min:0'],
            'stock_quantity' => ['required', 'integer', 'min:0'],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'image', 'max:4096'],
        ]);
    }

    /**
     * Stores the uploaded image directly under public/products (same layout the
     * original PHP app used), so no storage:link / public disk setup is needed.
     * Swap this for Storage::disk('public') if you'd rather use Laravel's
     * storage convention once the app is otherwise working.
     */
    private function storeImage(Request $request): string
    {
        $file = $request->file('image');
        $filename = time() . '_' . $file->getClientOriginalName();
        $file->move(public_path('product-images'), $filename);

        return 'products/' . $filename; // e.g. "products/1778125906_foo.jpg"
    }
}
