<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'category_id', 'name', 'name_ar', 'slug', 'sku', 'barcode', 'product_type',
        'short_description', 'full_description', 'regular_price', 'sale_price', 'cost_price',
        'tax_class', 'track_inventory', 'stock_quantity', 'reserved_quantity',
        'low_stock_threshold', 'allow_backorders', 'status', 'is_featured',
    ];

    protected $casts = [
        'regular_price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'track_inventory' => 'boolean',
        'allow_backorders' => 'boolean',
        'is_featured' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function variations(): HasMany
    {
        return $this->hasMany(ProductVariation::class);
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** Current selling price — sale price when set, otherwise regular price. */
    protected function currentPrice(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->sale_price ?? $this->regular_price,
        );
    }

    public function availableStock(): int
    {
        return max(0, $this->stock_quantity - $this->reserved_quantity);
    }

    public function stockStatus(): string
    {
        if (! $this->track_inventory) {
            return 'ok';
        }

        if ($this->availableStock() <= 0) {
            return $this->allow_backorders ? 'ok' : 'out';
        }

        return $this->availableStock() <= $this->low_stock_threshold ? 'low' : 'ok';
    }
}
