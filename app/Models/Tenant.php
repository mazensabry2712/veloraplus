<?php

namespace App\Models;

use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['name', 'legal_name', 'slug', 'status', 'industry', 'country_code', 'default_currency', 'timezone', 'locale', 'database_name', 'database_host', 'database_port', 'database_status', 'database_ready_at', 'database_provisioning_error', 'metadata'])]
class Tenant extends Model
{
    use HasFactory, HasUlids;

    protected $connection = 'central';

    public static function databaseNameFor(string $tenantId): string
    {
        return config('velora.tenancy.database_prefix', 'veloraplus_tenant_').Str::lower($tenantId);
    }

    protected function casts(): array
    {
        return [
            'database_ready_at' => 'datetime',
            'database_port' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function domains(): HasMany
    {
        return $this->hasMany(TenantDomain::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(TenantMembership::class);
    }

    public function entitlements(): HasMany
    {
        return $this->hasMany(TenantEntitlement::class);
    }

    public function accounts(): BelongsToMany
    {
        return $this->belongsToMany(
            PlatformAccount::class,
            'tenant_memberships',
            'tenant_id',
            'account_id'
        )->withPivot(['role_key', 'status', 'joined_at']);
    }
}
