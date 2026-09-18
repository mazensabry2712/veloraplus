<?php

namespace Database\Factories;

use App\Models\CompanySetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CompanySetting> */
class CompanySettingFactory extends Factory
{
    protected $model = CompanySetting::class;

    public function definition(): array
    {
        return [
            'key' => fake()->unique()->slug(3),
            'value' => fake()->sentence(),
            'type' => 'string',
        ];
    }
}
