<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'code', 'address', 'country_code', 'city', 'timezone', 'status', 'metadata'])]
class Location extends TenantModel
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function staff(): HasMany
    {
        return $this->hasMany(Staff::class);
    }
}
