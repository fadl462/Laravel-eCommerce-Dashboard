<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'country' => $this->country,
            'status' => $this->status,
            'orders_count' => $this->whenCounted('orders'),
            'total_spent' => $this->totalSpent(),
            'average_order_value' => $this->averageOrderValue(),
            'last_order_at' => $this->whenLoaded('orders', fn () => optional($this->orders->sortByDesc('created_at')->first())->created_at?->toIso8601String()),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
