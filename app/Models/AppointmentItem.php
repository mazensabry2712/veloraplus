<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'appointment_id',
    'service_id',
    'service_name',
    'duration_minutes',
    'quantity',
    'unit_price_minor',
    'currency',
    'line_total_minor',
    'metadata',
])]
class AppointmentItem extends TenantModel
{
    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'quantity' => 'integer',
            'unit_price_minor' => 'integer',
            'line_total_minor' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
