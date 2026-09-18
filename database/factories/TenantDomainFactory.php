<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\TenantDomain;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TenantDomain> */
class TenantDomainFactory extends Factory
{
    protected $model = TenantDomain::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'domain' => fake()->unique()->slug(2).'.velora.test',
            'type' => 'subdomain',
            'is_primary' => true,
            'status' => 'active',
            'verified_at' => now(),
        ];
    }

    public function custom(): static
    {
        return $this->state([
            'type' => 'custom',
            'is_primary' => false,
            'verified_at' => null,
        ]);
    }
}