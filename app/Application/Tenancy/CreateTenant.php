<?php

namespace App\Application\Tenancy;

use App\Models\PlatformAccount;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TenantMembership;
use Illuminate\Support\Facades\DB;

final class CreateTenant
{
    public function __construct(
        private readonly TenantProvisioner $provisioner,
    ) {}

    public function execute(
        PlatformAccount $owner,
        string $name,
        string $slug,
        ?string $domain = null,
        array $attributes = [],
    ): Tenant {
        $tenant = DB::connection('central')->transaction(function () use ($owner, $name, $slug, $domain, $attributes): Tenant {
            $tenant = Tenant::create([
                'name' => $name,
                'legal_name' => $attributes['legal_name'] ?? null,
                'slug' => $slug,
                'status' => 'provisioning',
                'industry' => $attributes['industry'] ?? null,
                'country_code' => $attributes['country_code'] ?? null,
                'default_currency' => $attributes['default_currency'] ?? 'USD',
                'timezone' => $attributes['timezone'] ?? 'UTC',
                'locale' => $attributes['locale'] ?? 'en',
                'database_name' => Tenant::databaseNameFor('pending-'.uniqid('', true)),
                'database_host' => config('database.connections.tenant_template.host'),
                'database_port' => (int) config('database.connections.tenant_template.port', 3306),
                'database_status' => 'pending',
                'metadata' => $attributes['metadata'] ?? [],
            ]);

            $tenant->forceFill([
                'database_name' => Tenant::databaseNameFor($tenant->getKey()),
            ])->save();

            TenantDomain::create([
                'tenant_id' => $tenant->getKey(),
                'domain' => $domain ?: $tenant->slug.'.'.config('velora.tenancy.base_domain'),
                'type' => 'subdomain',
                'is_primary' => true,
                'status' => 'active',
                'verified_at' => now(),
            ]);

            TenantMembership::create([
                'tenant_id' => $tenant->getKey(),
                'account_id' => $owner->getKey(),
                'role_key' => 'owner',
                'status' => 'active',
                'joined_at' => now(),
            ]);

            return $tenant;
        });

        return $this->provisioner->provision($tenant);
    }
}