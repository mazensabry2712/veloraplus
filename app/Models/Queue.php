<?php

namespace App\Models;

use App\Domain\Booking\QueueStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'location_id',
    'service_id',
    'business_date',
    'status',
    'next_position',
    'metadata',
])]
class Queue extends TenantModel
{
    use HasFactory, HasUlids;

    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'status' => QueueStatus::class,
            'next_position' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class)->withTrashed();
    }

    public function entries(): HasMany
    {
        return $this->hasMany(QueueEntry::class);
    }
}
