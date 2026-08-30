<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'email', 'phone', 'country', 'status'];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function totalSpent(): float
    {
        return (float) $this->orders()->where('payment_status', 'paid')->sum('total');
    }

    public function averageOrderValue(): float
    {
        $count = $this->orders()->where('payment_status', 'paid')->count();

        return $count > 0 ? round($this->totalSpent() / $count, 2) : 0.0;
    }
}
