<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditPaymentAttemptAllocation extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'credit_payment_attempt_id',
        'credit_installment_id',
        'amount_cents',
    ];

    protected $casts = [
        'amount_cents' => 'integer',
    ];

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(CreditPaymentAttempt::class, 'credit_payment_attempt_id');
    }

    public function installment(): BelongsTo
    {
        return $this->belongsTo(CreditInstallment::class, 'credit_installment_id');
    }
}