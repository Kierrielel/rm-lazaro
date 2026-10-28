<?php

namespace App\Models;

//use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table = 'users';

    protected $fillable = [
        'username',
        'email',
        'phone_no',
        'name',
        'password',
        'role',
        'province',
        'city',
        'unit',
        'barangay',
        'credit_due_day',
        'credit_approved',
        'credit_on_hold',
        'credit_limit',
    ];

    public function hasAddress(): bool
    {
        return !empty($this->province)
            && $this->province !== 'No Address'
            && !empty($this->city)
            && !empty($this->barangay);
    }

    public function getFullAddressAttribute(): string
    {
        return collect([$this->unit, $this->barangay, $this->city, $this->province])
            ->filter(fn($part) => filled($part) && $part !== 'No Address' && strtolower($part) !== 'n/a')
            ->implode(', ');
    }

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'created_at' => 'datetime',
        'credit_approved' => 'boolean',
        'credit_on_hold' => 'boolean',
        'credit_limit' => 'decimal:2',
    ];

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function cart()
    {
        return $this->hasMany(Cart::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function creditPaymentAttempts(): HasMany
    {
        return $this->hasMany(CreditPaymentAttempt::class);
    }

    public function unpaidCreditBalance(): float
    {
        return $this->orders()
            ->where('payment_method', 'paylater')
            ->where('order_status', '!=', 'cancelled')
            ->get()
            ->filter(fn(Order $order) => !$order->is_fully_paid)
            ->sum(fn(Order $order) => $order->balance);
    }

    public function hasOverdueCredit(): bool
    {
        $orders = $this->orders()
            ->where('payment_method', 'paylater')
            ->where('order_status', '!=', 'cancelled')
            ->with(['installments.payments'])
            ->get();

        foreach ($orders as $order) {
            if ($order->installments->isNotEmpty()) {
                foreach ($order->installments as $installment) {
                    $amountPaid = (float) $installment->payments->sum('amount');
                    $balance = max(0, (float) $installment->amount_due - $amountPaid);

                    if ($installment->due_date->isBefore(today()) && $balance > 0) {
                        return true;
                    }
                }

                continue;
            }
        }

        return false;
    }

    public function refreshCreditHold(): bool
    {
        $shouldBeOnHold = (bool) $this->credit_on_hold;

        if ($this->hasOverdueCredit()) {
            $shouldBeOnHold = true;
        } elseif ($shouldBeOnHold && $this->unpaidCreditBalance() <= 0) {
            $shouldBeOnHold = false;
        }

        if ((bool) $this->credit_on_hold !== $shouldBeOnHold) {
            $this->credit_on_hold = $shouldBeOnHold;
            $this->save();
        }

        return $this->credit_on_hold;
    }

    public function canUseCredit(float $amount): bool
    {
        if (
            !$this->credit_approved
            || $this->hasOverdueCredit()
            || $this->credit_limit === null
            || $this->credit_on_hold === true
            || $amount <= 0
        ) {
            return false;
        }
        return $this->unpaidCreditBalance() + $amount <= (float) $this->credit_limit;
    }

    public function monthlyPayableAmount(): float
    {
        return CreditInstallment::query()
            ->whereHas(
                'order',
                fn($query) => $query
                    ->where('user_id', $this->id)
                    ->where('payment_method', 'paylater')
                    ->where('order_status', '!=', 'cancelled')
            )
            ->whereBetween('due_date', [
                today()->startOfMonth(),
                today()->endOfMonth(),
            ])
            ->with('payments')
            ->get()
            ->sum(fn(CreditInstallment $installment) => $installment->balance);
    }

    public function creditApplications(): HasMany
    {
        return $this->hasMany(CreditApplication::class);
    }

    public function latestCreditApplication(): HasOne
    {
        return $this->hasOne(CreditApplication::class)->latestOfMany();
    }
}
