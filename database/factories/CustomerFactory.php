<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Customer> */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'customer_account_id' => null,
            'name' => fake()->name(),
            'phone' => fake()->e164PhoneNumber(),
            'email' => fake()->unique()->safeEmail(),
            'status' => 'active',
            'source' => 'manual',
            'notes' => null,
            'metadata' => [],
        ];
    }
}
