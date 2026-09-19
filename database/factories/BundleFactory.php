<?php

namespace Database\Factories;

use App\Models\Bundle;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Bundle> */
class BundleFactory extends Factory
{
    protected $model = Bundle::class;

    public function definition(): array
    {
        $key = 'bundle.'.Str::lower(Str::random(8));

        return [
            'key' => $key,
            'name' => Str::headline($key),
            'status' => 'draft',
            'discount_bps' => 0,
            'sort_order' => 0,
            'metadata' => null,
        ];
    }
}
