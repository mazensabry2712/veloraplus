<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable([
    'provider',
    'provider_event_id',
    'event_type',
    'status',
    'merchant_order_id',
    'transaction_id',
    'received_at',
    'processed_at',
    'payload_hash',
    'payload',
])]
class TenantPaymentWebhookEvent extends TenantModel
{
    protected function casts(): array
    {
        return [
            'received_at' => 'immutable_datetime',
            'processed_at' => 'immutable_datetime',
            'payload' => 'array',
        ];
    }
}
