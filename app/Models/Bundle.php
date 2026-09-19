<?php

namespace App\Models;

use Database\Factories\BundleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable(['key', 'name', 'description', 'status', 'discount_bps', 'sort_order', 'metadata'])]
class Bundle extends Model
{
    /** @use HasFactory<BundleFactory> */
    use HasFactory, HasUlids;

    protected $connection = 'central';

    protected function casts(): array
    {
        return [
            'discount_bps' => 'integer',
            'sort_order' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(Module::class, 'bundle_modules')->withTimestamps();
    }

    public function features(): BelongsToMany
    {
        return $this->belongsToMany(Feature::class, 'bundle_features')->withTimestamps();
    }

    public function prices(): MorphMany
    {
        return $this->morphMany(CatalogPrice::class, 'priceable');
    }
}
