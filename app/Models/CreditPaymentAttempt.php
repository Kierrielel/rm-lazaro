<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CreditPaymentAttempt extends Model
{
    protected $fillable = [
        'reference',
        'user_id',
        'method',
        'amount_cents',
        'currency',
        'status',
        'paymongo_payment_intent_id',
        'paymongo_checkout_session_id',
        'checkout_url',
        'paymongo_payment_id',
        'last_checked_at',
        'expires_at',
        'paid_at',
    ];

    protected $casts = [
        'amount_cents' => 'integer',
        'expires_at' => 'datetime',
        'paid_at' => 'datetime',
        'last_checked_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(CreditPaymentAttemptAllocation::class);
    }
}
