<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['staff_working_hour_id', 'starts_at', 'ends_at', 'label'])]
class StaffBreak extends TenantModel
{
    public function workingHour(): BelongsTo
    {
        return $this->belongsTo(StaffWorkingHour::class, 'staff_working_hour_id');
    }
}
