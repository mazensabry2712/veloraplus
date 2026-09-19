<?php

namespace App\Models;

use App\Domain\Billing\CreditStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'amount_minor',
    'remaining_minor',
    'currency',
    'status',
    'source',
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
            'amount_minor' => 'integer',
            'remaining_minor' => 'integer',
            'status' => CreditStatus::class,
            'expires_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
