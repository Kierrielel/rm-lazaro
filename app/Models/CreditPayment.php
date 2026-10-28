<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreditPayment extends Model
{
    protected $fillable = [
        'order_id',
        'credit_installment_id',
        'amount',
        'paid_at',
        'note',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
        'amount' => 'decimal:2',
    ];

    public $timestamps = false;

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function installment()
    {
        return $this->belongsTo(CreditInstallment::class, 'credit_installment_id');
    }
}
