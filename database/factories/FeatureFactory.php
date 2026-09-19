<?php

namespace Database\Factories;

use App\Models\Feature;
use App\Models\Module;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Feature> */
class FeatureFactory extends Factory
{
    protected $model = Feature::class;

    public function definition(): array
    {
        $key = 'feature.'.Str::lower(Str::random(8));

        return [
            'module_id' => Module::factory(),
            'key' => $key,
            'name' => Str::headline($key),
            'status' => 'draft',
            'billing_mode' => 'flat',
            'is_required' => false,
            'is_individually_purchasable' => true,
            'sort_order' => 0,
            'metadata' => null,
        ];
    }
}
