<?php

namespace App\Models;

use App\Domain\Booking\QueueEntryStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'queue_id',
    'customer_id',
    'appointment_id',
    'position',
    'status',
    'idempotency_key',
    'joined_at',
    'called_at',
    'completed_at',
    'skipped_at',
    'no_show_at',
    'notes',
    'metadata',
])]
class QueueEntry extends TenantModel
{
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'status' => QueueEntryStatus::class,
            'joined_at' => 'immutable_datetime',
            'called_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'skipped_at' => 'immutable_datetime',
            'no_show_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    public function queue(): BelongsTo
    {
        return $this->belongsTo(Queue::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }
}
