<?php

namespace App\Models;

use App\Domain\Billing\RefundStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'payment_id',
    'idempotency_key',
    'amount_minor',
    'currency',
    'status',
    'reason',
    'provider_refund_id',
    'initiated_by_account_id',
    'processed_at',
    'metadata',
])]
class PlatformRefund extends Model
{
    use HasUlids;

    protected $connection = 'central';

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'status' => RefundStatus::class,
            'processed_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(PlatformPayment::class, 'payment_id');
    }
}
