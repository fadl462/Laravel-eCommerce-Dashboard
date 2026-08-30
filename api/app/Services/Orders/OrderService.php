<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Inventory\InventoryService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function __construct(
        protected InventoryService $inventory,
        protected ActivityLogger $activityLogger,
    ) {
    }

    public function generateOrderNumber(): string
    {
        $last = Order::orderByDesc('id')->first();
        $next = $last ? ((int) str_replace('ORD-', '', $last->order_number) + 1) : 1001;

        return 'ORD-'.$next;
    }

    /**
     * Fulfillment status transitions are validated against Order::STATUS_FLOW
     * so the API can never be used to jump the pipeline. Every change is
     * written to order_status_histories, which is what powers the Order
     * Activity timeline in the dashboard.
     */
    public function transitionStatus(Order $order, string $newStatus, ?User $actor = null, ?string $note = null): Order
    {
        if (! $order->canTransitionTo($newStatus)) {
            throw ValidationException::withMessages([
                'status' => "Cannot move an order from [{$order->status}] to [{$newStatus}].",
            ]);
        }

        return DB::transaction(function () use ($order, $newStatus, $actor, $note) {
            $from = $order->status;
            $order->update(['status' => $newStatus]);

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'field' => 'status',
                'from_value' => $from,
                'to_value' => $newStatus,
                'note' => $note,
                'changed_by' => $actor?->id,
            ]);

            if ($newStatus === 'cancelled') {
                $this->inventory->releaseForOrder($order, $actor);
            }

            if ($newStatus === 'returned') {
                $this->inventory->restockForOrder($order, $actor);
            }

            $this->activityLogger->log($actor, 'Order status changed', 'Orders', $order, $order->order_number, [
                'from' => $from, 'to' => $newStatus,
            ]);

            return $order->fresh();
        });
    }

    /**
     * Called by every gateway service (Stripe webhook, PayPal webhook, or a
     * manually confirmed bank transfer) with the outcome of a payment attempt.
     * This is the ONE place `orders.payment_status` gets written, and it also
     * decides whether the fulfillment pipeline should auto-advance.
     */
    public function applyPaymentResult(Order $order, string $paymentStatus): Order
    {
        return DB::transaction(function () use ($order, $paymentStatus) {
            $from = $order->payment_status;
            $order->update(['payment_status' => $paymentStatus]);

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'field' => 'payment_status',
                'from_value' => $from,
                'to_value' => $paymentStatus,
                'changed_by' => Auth::id(),
            ]);

            // Paying for a still-pending order moves it into the fulfillment
            // pipeline automatically; nothing else about payment_status implies
            // an automatic fulfillment change (e.g. a refund does NOT auto-cancel
            // shipped goods — that's a deliberate admin decision).
            if ($paymentStatus === 'paid' && $order->status === 'pending') {
                $this->transitionStatus($order, 'confirmed', null, 'Auto-confirmed on successful payment');
            }

            $this->activityLogger->log(null, 'Payment status updated', 'Payments', $order, $order->order_number, [
                'from' => $from, 'to' => $paymentStatus,
            ]);

            return $order->fresh();
        });
    }
}
