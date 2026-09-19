<?php

namespace App\Models;

use App\Domain\Billing\InvoiceStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'tenant_id',
    'subscription_id',
    'number',
    'status',
    'currency',
    'subtotal_minor',
    'discount_minor',
    'tax_minor',
    'credit_minor',
    'total_minor',
    'issued_at',
    'due_at',
    'paid_at',
    'metadata',
])]
class PlatformInvoice extends Model
{
    use HasUlids;

    protected $connection = 'central';

    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'subtotal_minor' => 'integer',
            'discount_minor' => 'integer',
            'tax_minor' => 'integer',
            'credit_minor' => 'integer',
            'total_minor' => 'integer',
            'issued_at' => 'immutable_datetime',
            'due_at' => 'immutable_datetime',
            'paid_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PlatformInvoiceItem::class, 'invoice_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PlatformPayment::class, 'invoice_id');
    }
}