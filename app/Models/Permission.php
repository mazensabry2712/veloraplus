<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
{
    use HasUlids;

    protected $connection = 'central';

    protected $table = 'permissions';

    public $incrementing = false;

    protected $keyType = 'string';
}
