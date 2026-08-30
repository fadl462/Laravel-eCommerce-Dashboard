<?php

namespace App\Services\Inventory;

use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Everything that changes a product's stock goes through here, so every
 * change lands in inventory_movements automatically (the "Inventory Activity"
 * ledger in the dashboard). Current Stock - Reserved Stock = Available Stock
 * is enforced by whoever reads Product::availableStock(); this service only
 * ever writes to stock_quantity / reserved_quantity.
 */
class InventoryService
{
    /** Reserve stock at order placement time (order NOT yet paid). */
    public function reserveForOrder(Order $order, ?User $actor = null): void
    {
        DB::transaction(function () use ($order, $actor) {
            foreach ($order->items as $item) {
                if (! $item->product || ! $item->product->track_inventory) {
                    continue;
                }

                $product = $item->product()->lockForUpdate()->first();

                if (! $product->allow_backorders && $product->availableStock() < $item->quantity) {
                    throw ValidationException::withMessages([
                        'stock' => "Insufficient stock for {$product->name} (SKU {$product->sku}).",
                    ]);
                }

                $product->increment('reserved_quantity', $item->quantity);

                InventoryMovement::create([
                    'product_id' => $product->id,
                    'change' => -$item->quantity,
                    'reason' => 'order_placed',
                    'reference_type' => Order::class,
                    'reference_id' => $order->id,
                    'created_by' => $actor?->id,
                    'note' => "Reserved for {$order->order_number}",
                ]);
            }
        });
    }

    /** Order cancelled before shipment — release the reservation, stock never actually left. */
    public function releaseForOrder(Order $order, ?User $actor = null): void
    {
        DB::transaction(function () use ($order, $actor) {
            foreach ($order->items as $item) {
                if (! $item->product || ! $item->product->track_inventory) {
                    continue;
                }

                $product = $item->product()->lockForUpdate()->first();
                $product->decrement('reserved_quantity', min($item->quantity, $product->reserved_quantity));

                InventoryMovement::create([
                    'product_id' => $product->id,
                    'change' => $item->quantity,
                    'reason' => 'order_cancelled',
                    'reference_type' => Order::class,
                    'reference_id' => $order->id,
                    'created_by' => $actor?->id,
                    'note' => "Released reservation for cancelled {$order->order_number}",
                ]);
            }
        });
    }

    /** Order shipped — the reserved stock is now permanently deducted from stock_quantity. */
    public function commitForOrder(Order $order, ?User $actor = null): void
    {
        DB::transaction(function () use ($order, $actor) {
            foreach ($order->items as $item) {
                if (! $item->product || ! $item->product->track_inventory) {
                    continue;
                }

                $product = $item->product()->lockForUpdate()->first();
                $product->decrement('stock_quantity', $item->quantity);
                $product->decrement('reserved_quantity', min($item->quantity, $product->reserved_quantity));
            }
        });
    }

    /** Order returned after delivery — stock physically comes back. */
    public function restockForOrder(Order $order, ?User $actor = null): void
    {
        DB::transaction(function () use ($order, $actor) {
            foreach ($order->items as $item) {
                if (! $item->product) {
                    continue;
                }

                $product = $item->product()->lockForUpdate()->first();
                $product->increment('stock_quantity', $item->quantity);

                InventoryMovement::create([
                    'product_id' => $product->id,
                    'change' => $item->quantity,
                    'reason' => 'order_returned',
                    'reference_type' => Order::class,
                    'reference_id' => $order->id,
                    'created_by' => $actor?->id,
                    'note' => "Restocked from returned {$order->order_number}",
                ]);
            }
        });
    }

    public function manualAdjustment(Product $product, int $change, ?User $actor = null, ?string $note = null): Product
    {
        return DB::transaction(function () use ($product, $change, $actor, $note) {
            $locked = Product::whereKey($product->id)->lockForUpdate()->first();
            $locked->increment('stock_quantity', $change);

            InventoryMovement::create([
                'product_id' => $locked->id,
                'change' => $change,
                'reason' => 'manual_adjustment',
                'created_by' => $actor?->id,
                'note' => $note,
            ]);

            return $locked->fresh();
        });
    }
}
