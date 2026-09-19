<?php

namespace App\Models;

use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Entitlements\EntitlementStatus;
use Database\Factories\TenantEntitlementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'catalog_type',
    'catalog_key',
    'status',
    'source',
    'source_reference',
    'quantity',
    'starts_at',
    'ends_at',
    'metadata',
])]
class TenantEntitlement extends Model
{
    /** @use HasFactory<TenantEntitlementFactory> */
    use HasFactory, HasUlids;

    protected $connection = 'central';

    protected function casts(): array
    {
        return [
            'status' => EntitlementStatus::class,
            'source' => EntitlementSource::class,
            'quantity' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
