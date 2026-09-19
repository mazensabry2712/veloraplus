<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    public function workingHours(): HasMany
    {
        return $this->hasMany(StaffWorkingHour::class);
    }

    public function timeOffs(): HasMany
    {
        return $this->hasMany(StaffTimeOff::class);
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'staff_services');
    }
}
