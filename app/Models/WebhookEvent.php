<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'provider',
    'provider_event_id',
    'event_type',
    'status',
    'received_at',
    'processed_at',
    'payload_hash',
    'payload',
])]
class WebhookEvent extends Model
{
    use HasUlids;

    protected $connection = 'central';

    protected function casts(): array
    {
        return [
            'received_at' => 'immutable_datetime',
            'processed_at' => 'immutable_datetime',
            'payload' => 'array',
        ];
    }
}
