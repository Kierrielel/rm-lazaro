<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'category_id', 'name', 'brand', 'description',
        'price', 'stock_quantity', 'image_path',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    /** Resolves the stored image_path (e.g. "products/foo.jpg") to a public URL. */
    public function getImageUrlAttribute(): string
    {
        return $this->image_path
            ? asset('product-images/' . basename($this->image_path))
            : asset('images/logo.png');
    }

    public function inStock(int $quantity = 1): bool
    {
        return $this->stock_quantity >= $quantity;
    }
}
