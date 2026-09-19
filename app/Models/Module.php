<?php

namespace App\Models;

use Database\Factories\ModuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable(['key', 'name', 'description', 'status', 'is_core', 'sort_order', 'metadata'])]
class Module extends Model
{
    /** @use HasFactory<ModuleFactory> */
    use HasFactory, HasUlids;

    protected $connection = 'central';

    protected function casts(): array
    {
        return [
            'is_core' => 'boolean',
            'sort_order' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function features(): HasMany
    {
        return $this->hasMany(Feature::class);
    }

    public function dependencies(): MorphMany
    {
        return $this->morphMany(CatalogDependency::class, 'dependent');
    }

    public function prices(): MorphMany
    {
        return $this->morphMany(CatalogPrice::class, 'priceable');
    }

    public function bundles(): BelongsToMany
    {
        return $this->belongsToMany(Bundle::class, 'bundle_modules')->withTimestamps();
    }
}
