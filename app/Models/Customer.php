<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['customer_account_id', 'name', 'phone', 'email', 'status', 'source', 'notes', 'metadata'])]
class Customer extends TenantModel
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }
}
