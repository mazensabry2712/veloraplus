<?php

namespace App\Models;

use App\Domain\Payments\TenantPaymentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'appointment_id',
    'customer_id',
    'provider',
    'merchant_order_id',
    'provider_payment_id',
    'provider_order_id',
    'provider_session_id',
    'amount_minor',
    'currency',
    'status',
    'paid_at',
    'failed_at',
    'refunded_at',
    'metadata',
])]
class TenantPayment extends TenantModel
{
    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'status' => TenantPaymentStatus::class,
            'paid_at' => 'datetime',
            'failed_at' => 'datetime',
            'refunded_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
