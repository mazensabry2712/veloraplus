<?php

namespace Database\Factories;

use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Service> */
class ServiceFactory extends Factory
{
    protected $model = Service::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'description' => fake()->sentence(),
            'duration_minutes' => fake()->randomElement([15, 30, 45, 60]),
            'buffer_before_minutes' => 0,
            'buffer_after_minutes' => 0,
            'price_minor' => fake()->numberBetween(5000, 50000),
            'currency' => 'EGP',
            'deposit_amount_minor' => 0,
            'status' => 'active',
            'online_bookable' => true,
            'capacity' => 1,
            'metadata' => [],
        ];
    }
}
