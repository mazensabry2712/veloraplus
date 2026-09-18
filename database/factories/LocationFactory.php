<?php

namespace Database\Factories;

use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Location> */
class LocationFactory extends Factory
{
    protected $model = Location::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company().' Branch',
            'code' => strtoupper(fake()->unique()->bothify('BR-###')),
            'address' => fake()->address(),
            'country_code' => 'EG',
            'city' => 'Cairo',
            'timezone' => 'Africa/Cairo',
            'status' => 'active',
            'metadata' => [],
        ];
    }
}
