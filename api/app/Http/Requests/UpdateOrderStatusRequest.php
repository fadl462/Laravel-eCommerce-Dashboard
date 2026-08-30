<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('orders.edit');
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'in:pending,confirmed,processing,ready_to_ship,shipped,delivered,cancelled,returned,completed'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
