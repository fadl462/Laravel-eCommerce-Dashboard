<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('products.create');
    }

    public function rules(): array
    {
        return [
            'category_id' => ['nullable', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:100', 'unique:products,sku'],
            'barcode' => ['nullable', 'string', 'max:100'],
            'product_type' => ['required', 'in:simple,variable'],
            'short_description' => ['nullable', 'string'],
            'full_description' => ['nullable', 'string'],
            'regular_price' => ['required', 'numeric', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'min:0', 'lt:regular_price'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'tax_class' => ['nullable', 'string'],
            'track_inventory' => ['boolean'],
            'stock_quantity' => ['required_if:track_inventory,true', 'integer', 'min:0'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0'],
            'allow_backorders' => ['boolean'],
            'status' => ['required', 'in:active,draft,inactive'],
            'is_featured' => ['boolean'],
            'images' => ['nullable', 'array'],
            'images.*' => ['image', 'max:4096'],
            'variations' => ['nullable', 'array'],
            'variations.*.sku' => ['required_with:variations', 'string'],
            'variations.*.price' => ['required_with:variations', 'numeric', 'min:0'],
            'variations.*.stock_quantity' => ['required_with:variations', 'integer', 'min:0'],
            'variations.*.attribute_values' => ['required_with:variations', 'array'],
        ];
    }
}
