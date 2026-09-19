<?php

namespace App\Models;

use App\Domain\Billing\SubscriptionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'tenant_id',
    'status',
    'billing_cycle',
    'currency',
    'country_code',
    'starts_at',
    'trial_ends_at',
    'current_period_start',
    'current_period_end',
    'next_billed_at',
    'cancel_at_period_end',
    'cancelled_at',
    'provider',
    'provider_reference',
    'subtotal_minor',
    'discount_minor',
    'tax_minor',
    'total_minor',
    'metadata',
])]
class Subscription extends Model
{
    use HasUlids;

    protected $connection = 'central';

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'starts_at' => 'immutable_datetime',
            'trial_ends_at' => 'immutable_datetime',
            'current_period_start' => 'immutable_datetime',
            'current_period_end' => 'immutable_datetime',
            'next_billed_at' => 'immutable_datetime',
            'cancel_at_period_end' => 'boolean',
            'cancelled_at' => 'immutable_datetime',
            'subtotal_minor' => 'integer',
            'discount_minor' => 'integer',
            'tax_minor' => 'integer',
            'total_minor' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SubscriptionItem::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(PlatformInvoice::class);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [
            SubscriptionStatus::PendingPayment,
            SubscriptionStatus::Trialing,
            SubscriptionStatus::Active,
            SubscriptionStatus::PastDue,
            SubscriptionStatus::Grace,
            SubscriptionStatus::Suspended,
        ], true);
    }
}
