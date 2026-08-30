<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    protected $fillable = [
        'order_number', 'customer_id', 'subtotal', 'shipping_amount', 'tax_amount',
        'discount_amount', 'total', 'currency', 'payment_status', 'status',
        'shipping_address_line1', 'shipping_city', 'shipping_country', 'shipping_postal_code',
        'billing_same_as_shipping', 'notes',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'shipping_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'billing_same_as_shipping' => 'boolean',
    ];

    /**
     * Fulfillment statuses that are valid next steps FROM the given status.
     * Enforced in OrderService::transitionStatus() so the API can't be used
     * to skip the pipeline (e.g. "pending" straight to "delivered").
     */
    public const STATUS_FLOW = [
        'pending' => ['confirmed', 'cancelled'],
        'confirmed' => ['processing', 'cancelled'],
        'processing' => ['ready_to_ship', 'cancelled'],
        'ready_to_ship' => ['shipped', 'cancelled'],
        'shipped' => ['delivered', 'cancelled'],
        'delivered' => ['completed', 'returned'],
        'completed' => ['returned'],
        'cancelled' => [],
        'returned' => [],
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function shipment(): HasOne
    {
        return $this->hasOne(Shipment::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function canTransitionTo(string $newStatus): bool
    {
        return in_array($newStatus, self::STATUS_FLOW[$this->status] ?? [], true);
    }
}
