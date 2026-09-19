<?php

namespace App\Models;

use App\Domain\Payments\TenantPaymentRefundStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_payment_id',
    'idempotency_key',
    'amount_minor',
    'currency',
    'status',
    'provider_refund_id',
    'reason',
    'requested_at',
    'completed_at',
    'failed_at',
    'metadata',
])]
class TenantPaymentRefund extends TenantModel
{
    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'status' => TenantPaymentRefundStatus::class,
            'requested_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(TenantPayment::class, 'tenant_payment_id');
    }
}
