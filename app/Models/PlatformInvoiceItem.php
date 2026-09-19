<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'invoice_id',
    'subscription_item_id',
    'description',
    'catalog_type',
    'catalog_key',
    'quantity',
    'unit_amount_minor',
    'discount_minor',
    'tax_minor',
    'line_total_minor',
    'metadata',
])]
class PlatformInvoiceItem extends Model
{
    use HasUlids;

    protected $connection = 'central';

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_amount_minor' => 'integer',
            'discount_minor' => 'integer',
            'tax_minor' => 'integer',
            'line_total_minor' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(PlatformInvoice::class, 'invoice_id');
    }

    public function subscriptionItem(): BelongsTo
    {
        return $this->belongsTo(SubscriptionItem::class);
    }
}