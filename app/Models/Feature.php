<?php

namespace App\Models;

use Database\Factories\FeatureFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable(['module_id', 'key', 'name', 'description', 'status', 'billing_mode', 'is_required', 'is_individually_purchasable', 'sort_order', 'metadata'])]
class Feature extends Model
{
    /** @use HasFactory<FeatureFactory> */
    use HasFactory, HasUlids;

    protected $connection = 'central';

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'is_individually_purchasable' => 'boolean',
            'sort_order' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
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
        return $this->belongsToMany(Bundle::class, 'bundle_features')->withTimestamps();
    }
}
