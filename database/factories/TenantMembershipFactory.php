<?php

namespace Database\Factories;

use App\Models\PlatformAccount;
use App\Models\Tenant;
use App\Models\TenantMembership;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TenantMembership> */
class TenantMembershipFactory extends Factory
{
    protected $model = TenantMembership::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'account_id' => PlatformAccount::factory(),
            'role_key' => 'owner',
            'status' => 'active',
            'joined_at' => now(),
            'invitation_metadata' => null,
        ];
    }

    public function manager(): static
    {
        return $this->state(['role_key' => 'manager']);
    }
}