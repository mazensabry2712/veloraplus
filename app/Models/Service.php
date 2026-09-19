<?php

namespace App\Models;

use App\Domain\Booking\ServiceStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'name',
    'description',
    'duration_minutes',
    'buffer_before_minutes',
    'buffer_after_minutes',
    'price_minor',
    'currency',
    'deposit_amount_minor',
    'status',
    'online_bookable',
    'capacity',
    'metadata',
])]
class Service extends TenantModel
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'buffer_before_minutes' => 'integer',
            'buffer_after_minutes' => 'integer',
            'price_minor' => 'integer',
            'deposit_amount_minor' => 'integer',
            'status' => ServiceStatus::class,
            'online_bookable' => 'boolean',
            'capacity' => 'integer',
            'metadata' => 'array',
        ];
    }
}
