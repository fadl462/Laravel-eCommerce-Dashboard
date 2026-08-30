<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmBankTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('payments.verify');
    }

    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:500'], // used only on reject
        ];
    }
}
