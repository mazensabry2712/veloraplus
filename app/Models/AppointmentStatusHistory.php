<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'appointment_id',
    'from_status',
    'to_status',
    'reason',
    'changed_at',
    'metadata',
])]
class AppointmentStatusHistory extends TenantModel
{
    protected function casts(): array
    {
        return [
            'changed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }
}
