<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'total_amount',
        'order_status',
        'order_date',
        'payment_method',
        'due_date',
        'credit_term_months',
        'credit_interest_rate',
        'credit_finance_charge',
    ];

    protected $casts = [
        'order_date' => 'datetime',
        'total_amount' => 'decimal:2',
        'due_date' => 'date',
        'credit_interest_rate' => 'decimal:2',
        'credit_finance_charge' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments()
    {
        return $this->hasMany(CreditPayment::class);
    }

    public function installments()
    {
        return $this->hasMany(CreditInstallment::class)->orderBy('installment_number');
    }

    public function getAmountPaidAttribute(): float
    {
        return (float) $this->payments()->sum('amount');
    }

    public function getPayableAmountAttribute(): float
    {
        return round(
            (float) $this->total_amount + (float) ($this->credit_finance_charge ?? 0),
            2
        );
    }

    public function getBalanceAttribute(): float
    {
        return max(0, $this->payable_amount - $this->amount_paid);
    }

    public function getIsFullyPaidAttribute(): bool
    {
        return $this->balance <= 0;
    }
}
