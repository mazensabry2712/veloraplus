<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['dependent_type', 'dependent_id', 'dependency_type', 'dependency_id', 'metadata'])]
class CatalogDependency extends Model
{
    use HasUlids;

    protected $connection = 'central';

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function dependent(): MorphTo
    {
        return $this->morphTo();
    }

    public function dependency(): MorphTo
    {
        return $this->morphTo();
    }
}
