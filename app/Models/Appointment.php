<?php

namespace App\Models;

use App\Domain\Booking\AppointmentPaymentStatus;
use App\Domain\Booking\AppointmentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'customer_id',
    'staff_id',
    'location_id',
    'starts_at',
    'ends_at',
    'blocked_starts_at',
    'blocked_ends_at',
    'status',
    'payment_status',
    'idempotency_key',
    'cancellation_reason',
    'cancelled_at',
    'notes',
    'metadata',
])]
class Appointment extends TenantModel
{
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'blocked_starts_at' => 'datetime',
            'blocked_ends_at' => 'datetime',
            'status' => AppointmentStatus::class,
            'payment_status' => AppointmentPaymentStatus::class,
            'cancelled_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(AppointmentItem::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(AppointmentStatusHistory::class)->orderBy('changed_at');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(TenantPayment::class);
    }
}
