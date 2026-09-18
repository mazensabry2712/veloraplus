<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Tenant> */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        $id = (string) Str::ulid();

        return [
            'id' => $id,
            'name' => fake()->company(),
            'legal_name' => null,
            'slug' => fake()->unique()->slug(2),
            'status' => 'active',
            'industry' => fake()->randomElement(['healthcare', 'beauty', 'education', 'professional_services']),
            'country_code' => 'EG',
            'default_currency' => 'EGP',
            'timezone' => 'Africa/Cairo',
            'locale' => 'en',
            'database_name' => Tenant::databaseNameFor($id),
            'database_host' => env('TENANT_DB_HOST', '127.0.0.1'),
            'database_port' => (int) env('TENANT_DB_PORT', 3306),
            'database_status' => 'ready',
            'database_ready_at' => now(),
            'metadata' => [],
        ];
    }

    public function provisioning(): static
    {
        return $this->state([
            'status' => 'provisioning',
            'database_status' => 'pending',
            'database_ready_at' => null,
        ]);
    }
}