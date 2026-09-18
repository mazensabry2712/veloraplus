<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

abstract class TenantModel extends Model
{
    use HasUlids;

    protected $connection = 'tenant';
}
