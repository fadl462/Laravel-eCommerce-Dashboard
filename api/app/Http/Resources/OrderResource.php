<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'customer' => $this->whenLoaded('customer', fn () => [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
                'email' => $this->customer->email,
            ]),
            'items_count' => $this->whenCounted('items'),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'subtotal' => (float) $this->subtotal,
            'shipping_amount' => (float) $this->shipping_amount,
            'tax_amount' => (float) $this->tax_amount,
            'discount_amount' => (float) $this->discount_amount,
            'total' => (float) $this->total,
            'currency' => $this->currency,

            // Kept as two distinct fields end-to-end — never collapsed into one
            // "status" — matching the payment/fulfillment separation in the spec.
            'payment_status' => $this->payment_status,
            'status' => $this->status,

            'available_next_statuses' => array_values(\App\Models\Order::STATUS_FLOW[$this->status] ?? []),
            'shipping_address' => [
                'line1' => $this->shipping_address_line1,
                'city' => $this->shipping_city,
                'country' => $this->shipping_country,
                'postal_code' => $this->shipping_postal_code,
            ],
            'billing_same_as_shipping' => $this->billing_same_as_shipping,
            'payment' => new PaymentResource($this->whenLoaded('payment')),
            'status_history' => $this->whenLoaded('statusHistories', fn () => $this->statusHistories->map(fn ($h) => [
                'field' => $h->field,
                'from' => $h->from_value,
                'to' => $h->to_value,
                'note' => $h->note,
                'changed_by' => $h->changedBy?->name,
                'at' => $h->created_at->toIso8601String(),
            ])),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
