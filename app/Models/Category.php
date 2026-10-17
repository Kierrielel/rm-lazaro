<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = ['name', 'description', 'image_path'];

    public function products()
    {
        return $this->hasMany(Product::class);
    }
    public function getImageUrlAttribute(): string
    {
        return $this->image_path
            ? asset('category-images/' . basename($this->image_path))
            : asset('images/logo.png');
    }
}
