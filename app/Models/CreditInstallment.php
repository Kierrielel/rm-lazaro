<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CreditInstallment extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'order_id',
        'installment_number',
        'due_date',
        'amount_due',
    ];

    protected $casts = [
        'due_date' => 'date',
        'amount_due' => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(CreditPayment::class);
    }

    public function getAmountPaidAttribute(): float
    {
        return (float) $this->payments()->sum('amount');
    }

    public function getBalanceAttribute(): float
    {
        return max(0, (float) $this->amount_due - $this->amount_paid);
    }

    public function getIsPaidAttribute(): bool
    {
        return $this->balance <= 0;
    }

    public function paymentAttemptAllocations(): HasMany
    {
        return $this->hasMany(CreditPaymentAttemptAllocation::class);
    }
}
