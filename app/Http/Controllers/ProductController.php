<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function home()
    {
        $featured = Product::with('category')->latest('created_at')->take(8)->get();
        $categories = Category::withCount('products')->orderBy('name')->get();
        return view('customer.home', compact('featured', 'categories'));
    }

    public function index(Request $request)
    {
        $query = Product::with('category');

        if ($search = $request->get('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        if ($categoryId = $request->get('category')) {
            $query->where('category_id', $categoryId);
        }

        $products = $query->orderBy('name')->paginate(12)->withQueryString();
        $categories = Category::orderBy('name')->get();

        return view('customer.products', compact('products', 'categories'));
    }

    public function show(Product $product)
    {
        $product->load('variants');
        return view('customer.product_page', compact('product'));
    }
}
