<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

#[Hidden(['encrypted_credentials'])]
#[Fillable([
    'tenant_id',
    'provider',
    'account_reference',
    'status',
    'encrypted_credentials',
    'metadata',
])]
class PaymentProviderAccount extends Model
{
    use HasFactory, HasUlids;

    protected $connection = 'central';

    protected function casts(): array
    {
        return [
            'encrypted_credentials' => 'encrypted:array',
            'metadata' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
