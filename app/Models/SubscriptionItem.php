<?php

namespace App\Models;

use App\Domain\Billing\SubscriptionItemStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'subscription_id',
    'item_type',
    'catalog_type',
    'catalog_key',
    'catalog_price_id',
    'quantity',
    'unit_amount_minor',
    'currency',
    'discount_amount_minor',
    'tax_amount_minor',
    'total_amount_minor',
    'status',
    'activation_source',
    'starts_at',
    'ends_at',
    'metadata',
])]
class SubscriptionItem extends Model
{
    use HasUlids;

    protected $connection = 'central';

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_amount_minor' => 'integer',
            'discount_amount_minor' => 'integer',
            'tax_amount_minor' => 'integer',
            'total_amount_minor' => 'integer',
            'status' => SubscriptionItemStatus::class,
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function catalogPrice(): BelongsTo
    {
        return $this->belongsTo(CatalogPrice::class);
    }
}