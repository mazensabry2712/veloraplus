<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['key', 'value', 'type'])]
class CompanySetting extends TenantModel
{
}
