<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'actor_account_id',
    'action',
    'auditable_type',
    'auditable_id',
    'from_status',
    'to_status',
    'metadata',
])]
class BillingAudit extends Model
{
    use HasUlids;

    protected $connection = 'central';

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(PlatformAccount::class, 'actor_account_id');
    }
}