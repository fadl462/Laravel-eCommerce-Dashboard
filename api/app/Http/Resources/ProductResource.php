<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'name_ar' => $this->name_ar,
            'slug' => $this->slug,
            'sku' => $this->sku,
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category->id,
                'name' => $this->category->name,
            ]),
            'price' => (float) $this->regular_price,
            'sale_price' => $this->sale_price ? (float) $this->sale_price : null,
            'current_price' => (float) $this->current_price,
            'stock' => $this->stock_quantity,
            'reserved' => $this->reserved_quantity,
            'available' => $this->availableStock(),
            'low_stock_threshold' => $this->low_stock_threshold,
            'stock_status' => $this->stockStatus(), // "ok" | "low" | "out" — matches the dashboard's status badges
            'status' => $this->status,
            'is_featured' => $this->is_featured,
            'images' => ProductImageResource::collection($this->whenLoaded('images')),
            'variations' => $this->whenLoaded('variations', fn () => $this->variations->map(fn ($v) => [
                'id' => $v->id,
                'sku' => $v->sku,
                'price' => (float) $v->price,
                'stock' => $v->stock_quantity,
                'attributes' => $v->attribute_values,
            ])),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
