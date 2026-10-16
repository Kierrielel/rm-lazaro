<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductVariant extends Model
{
    protected $fillable = ['product_id', 'name', 'options', 'price', 'stock_quantity', 'image_path'];

    protected $casts = [
        'options' => 'array',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function getEffectivePriceAttribute()
    {
        return $this->price ?? $this->product->price;
    }

    public function getImageUrlAttribute(): string
    {
        return $this->image_path
            ? asset('product-images/' . basename($this->image_path))
            : $this->product->image_url;
    }
}
