<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

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
        $validated = $this->validateProduct($request);

        DB::transaction(function () use ($request, $validated) {
            $attributes = $this->productAttributes($validated, $request);
            $product = Product::create($attributes);
            $this->syncVariants($product, $attributes['variation_options'], $request->input('variants', []));
        });

        return redirect()->route('admin.inventory')->with('success', 'Product added.');
    }

    public function edit(Product $product)
    {
        $product->load('variants');
        $categories = Category::orderBy('name')->get();
        return view('admin.edit_product', compact('product', 'categories'));
    }

    public function update(Request $request, Product $product)
    {
        $validated = $this->validateProduct($request, $product->id);

        DB::transaction(function () use ($request, $validated, $product) {
            $attributes = $this->productAttributes($validated, $request);
            $product->update($attributes);
            $this->syncVariants($product, $attributes['variation_options'], $request->input('variants', []));
        });

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

            'option_types' => ['nullable', 'array'],
            'option_types.*.name' => ['nullable', 'string', 'max:50'],
            'option_types.*.values' => ['nullable', 'string', 'max:500'],

            'variants' => ['nullable', 'array'],
            'variants.*.id' => ['nullable', 'integer'],
            'variants.*.options' => ['required', 'json'],
            'variants.*.price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.stock_quantity' => ['required', 'numeric', 'min:0'],
        ]);
    }

    private function productAttributes(array $validated, Request $request): array
    {
        $attributes = Arr::only($validated, [
            'name', 'brand', 'category_id', 'price', 'stock_quantity', 'description',
        ]);

        $attributes['variation_options'] = $this->buildVariationOptions($validated['option_types'] ?? []);

        if ($request->hasFile('image')) {
            $attributes['image_path'] = $this->storeImage($request);
        }

        return $attributes;
    }

    private function buildVariationOptions(array $types): ?array
    {
        $options = [];

        foreach ($types as $type) {
            $name = trim($type['name'] ?? '');
            $values = collect(explode(',', $type['values'] ?? ''))
                ->map(fn ($value) => trim($value))
                ->filter()
                ->unique()
                ->values()
                ->all();

            if ($name !== '' && $values) {
                $options[$name] = $values;
            }
        }

        return $options ?: null;
    }

    private function syncVariants(Product $product, ?array $variationOptions, array $rows): void
    {
        $keep = [];

        if ($variationOptions) {
            foreach ($rows as $row) {
                $options = json_decode($row['options'], true);
                if (!is_array($options) || !$options) {
                    continue;
                }

                $attributes = [
                    'name' => implode(' / ', $options),
                    'options' => $options,
                    'price' => ($row['price'] ?? '') === '' ? null : $row['price'],
                    'stock_quantity' => $row['stock_quantity'],
                ];

                $variant = !empty($row['id']) ? $product->variants()->find($row['id']) : null;

                if ($variant) {
                    $variant->update($attributes);
                } else {
                    $variant = $product->variants()->create($attributes);
                }

                $keep[] = $variant->id;
            }
        }

        $product->variants()->whereNotIn('id', $keep)->delete();
    }

    private function storeImage(Request $request): string
    {
        $file = $request->file('image');
        $filename = time() . '_' . $file->getClientOriginalName();
        $file->move(public_path('product-images'), $filename);

        return 'product-images/' . $filename;
    }
}