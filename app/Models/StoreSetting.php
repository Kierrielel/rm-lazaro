<?php

namespace App\Models;

use Database\Factories\StoreSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StoreSetting extends Model
{
    /** @use HasFactory<StoreSettingFactory> */
    use HasFactory;

    public const DEFAULTS = [
        'store_name' => 'RM LAZARO ENTERPRISES',
        'store_address' => '4XPH+86P, Magsaysay Rd, San Miguel, Bulacan',
        'contact_phone' => '0917 982 8985',
        'contact_email' => null,
        'opening_hours' => 'Monday - Saturday: 7:00 AM - 5:00 PM',
        'low_stock_threshold' => 20,
    ];

    protected $attributes = self::DEFAULTS;

    protected $fillable = ['store_name', 'store_address', 'contact_phone', 'contact_email', 'opening_hours', 'low_stock_threshold'];

    protected $casts = ['low_stock_threshold' => 'integer'];

    public static function current(): self
    {
        $settings = static::query()->find(1);
        if ($settings) {
            return $settings;
        }
        $settings = new self;
        $settings->id = 1;

        return $settings;
    }
}
