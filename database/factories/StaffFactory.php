<?php

namespace Database\Factories;

use App\Models\Location;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Staff> */
class StaffFactory extends Factory
{
    protected $model = Staff::class;

    public function definition(): array
    {
        return [
            'account_id' => null,
            'location_id' => null,
            'name' => fake()->name(),
            'phone' => fake()->e164PhoneNumber(),
            'email' => fake()->unique()->safeEmail(),
            'status' => 'active',
            'metadata' => [],
        ];
    }

    public function forLocation(?Location $location = null): static
    {
        return $this->state([
            'location_id' => $location?->getKey() ?? Location::factory(),
        ]);
    }
}
