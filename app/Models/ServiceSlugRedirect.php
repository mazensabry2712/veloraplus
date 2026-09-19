<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['service_id', 'old_slug', 'new_slug'])]
class ServiceSlugRedirect extends TenantModel
{
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
