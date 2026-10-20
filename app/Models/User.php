<?php

namespace App\Models;

//use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

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
}
