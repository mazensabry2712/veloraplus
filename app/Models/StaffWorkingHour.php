<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['staff_id', 'day_of_week', 'starts_at', 'ends_at'])]
class StaffWorkingHour extends TenantModel
{
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function breaks(): HasMany
    {
        return $this->hasMany(StaffBreak::class);
    }
}
