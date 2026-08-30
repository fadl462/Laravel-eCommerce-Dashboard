<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentWebhookLog extends Model
{
    protected $fillable = ['gateway', 'event_type', 'event_id', 'payload', 'processed', 'processing_error'];

    protected $casts = [
        'payload' => 'array',
        'processed' => 'boolean',
    ];
}
