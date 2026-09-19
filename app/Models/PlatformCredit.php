<?php

namespace App\Models;

use App\Domain\Billing\CreditEntryType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'entry_type',
    'amount_minor',
    'currency',
    'source_type',
    'source_id',
    'reference_type',
    'reference_id',
    'expires_at',
    'metadata',
])]
class PlatformCredit extends Model
{
    use HasUlids;

    protected $connection = 'central';

    protected function casts(): array
    {
        return [
            'entry_type' => CreditEntryType::class,
            'amount_minor' => 'integer',
            'expires_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}