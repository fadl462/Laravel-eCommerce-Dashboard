<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShippingMethod extends Model
{
    protected $fillable = ['shipping_zone_id', 'name', 'price', 'free_shipping_threshold', 'estimated_delivery', 'status'];

    protected $casts = [
        'price' => 'decimal:2',
        'free_shipping_threshold' => 'decimal:2',
    ];

    public function zone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class, 'shipping_zone_id');
    }
}
