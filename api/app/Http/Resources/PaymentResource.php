<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if (! $this->resource) {
            return [];
        }

        return [
            'id' => $this->id,
            'gateway' => $this->gateway,
            'amount' => (float) $this->amount,
            'currency' => $this->currency,
            'status' => $this->status,
            'transaction_reference' => $this->transaction_reference,
            'paid_at' => $this->paid_at?->toIso8601String(),
        ];
    }
}
