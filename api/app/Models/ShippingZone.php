<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShippingZone extends Model
{
    protected $fillable = ['name', 'countries', 'status'];

    protected $casts = ['countries' => 'array'];

    public function methods(): HasMany
    {
        return $this->hasMany(ShippingMethod::class);
    }
}
