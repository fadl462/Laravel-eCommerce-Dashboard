<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IssueRefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('payments.refund');
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
