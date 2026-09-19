<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['priceable_type', 'priceable_id', 'billing_cycle', 'currency', 'country_code', 'amount_minor', 'status', 'effective_from', 'effective_to', 'metadata'])]
class CatalogPrice extends Model
{
    use HasUlids;

    protected $connection = 'central';

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'effective_from' => 'datetime',
            'effective_to' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function priceable(): MorphTo
    {
        return $this->morphTo();
    }
}
