<?php

namespace App\Models;

use Database\Factories\TenantMembershipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'account_id', 'role_key', 'status', 'joined_at', 'invitation_metadata'])]
class TenantMembership extends Model
{
    use HasFactory, HasUlids;

    protected $connection = 'central';

    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
            'invitation_metadata' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(PlatformAccount::class, 'account_id');
    }
}