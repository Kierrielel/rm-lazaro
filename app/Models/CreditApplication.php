<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditApplication extends Model
{
    protected $fillable = [
        'first_name',
        'middle_name',
        'last_name',
        'phone_number',
        'province',
        'city',
        'barangay',
        'street',
        'credit_due_day',
        'monthly_income',
        'id_type',
        'terms_version',
        'terms_accepted_at',
    ];

    protected $casts = [
        'monthly_income' => 'decimal:2',
        'reviewed_at' => 'datetime',
        'terms_accepted_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
