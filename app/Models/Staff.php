<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['account_id', 'location_id', 'name', 'phone', 'email', 'status', 'metadata'])]
class Staff extends TenantModel
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
