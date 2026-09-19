<?php

namespace App\Models;

use Database\Factories\PlatformAccountFactory;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'status'])]
#[Hidden(['password', 'remember_token'])]
class PlatformAccount extends Authenticatable implements MustVerifyEmailContract
{
    /** @use HasFactory<PlatformAccountFactory> */
    use HasFactory, HasRoles, HasUlids, MustVerifyEmailTrait, Notifiable;

    protected $connection = 'central';

    protected $table = 'platform_accounts';

    public function memberships(): HasMany
    {
        return $this->hasMany(TenantMembership::class, 'account_id');
    }

    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(
            Tenant::class,
            'tenant_memberships',
            'account_id',
            'tenant_id'
        )->withPivot(['role_key', 'status', 'joined_at']);
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
