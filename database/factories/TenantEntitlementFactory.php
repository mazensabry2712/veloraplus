<?php

namespace Database\Factories;

use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Entitlements\EntitlementStatus;
use App\Models\Feature;
use App\Models\Module;
use App\Models\Tenant;
use App\Models\TenantEntitlement;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<TenantEntitlement> */
class TenantEntitlementFactory extends Factory
{
    protected $model = TenantEntitlement::class;

    public function definition(): array
    {
        $key = 'module.'.Str::lower(Str::random(8));

        return [
            'tenant_id' => Tenant::factory(),
            'catalog_type' => 'module',
            'catalog_key' => $key,
            'status' => EntitlementStatus::Active->value,
            'source' => EntitlementSource::Manual->value,
            'source_reference' => null,
            'quantity' => null,
            'starts_at' => CarbonImmutable::now()->subMinute(),
            'ends_at' => null,
            'metadata' => null,
        ];
    }

    public function forModule(Module $module): static
    {
        return $this->state([
            'catalog_type' => $module->getMorphClass(),
            'catalog_key' => $module->key,
        ]);
    }

    public function forFeature(Feature $feature): static
    {
        return $this->state([
            'catalog_type' => $feature->getMorphClass(),
            'catalog_key' => $feature->key,
        ]);
    }

    public function scheduledForRemoval(CarbonImmutable $endsAt): static
    {
        return $this->state([
            'status' => EntitlementStatus::ScheduledForRemoval->value,
            'ends_at' => $endsAt,
        ]);
    }

    public function inactive(): static
    {
        return $this->state([
            'status' => EntitlementStatus::Inactive->value,
        ]);
    }
}
