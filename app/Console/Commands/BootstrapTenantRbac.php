<?php

namespace App\Console\Commands;

use App\Application\Authorization\TenantRbacBootstrapper;
use App\Models\Tenant;
use Illuminate\Console\Command;

final class BootstrapTenantRbac extends Command
{
    protected $signature = 'tenants:bootstrap-rbac {tenant? : Tenant ULID or slug to bootstrap; omit for all active tenants}';

    protected $description = 'Bootstrap default RBAC roles and permissions for tenant workspaces';

    public function __construct(
        private readonly TenantRbacBootstrapper $bootstrapper,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $identifier = $this->argument('tenant');

        $tenants = $identifier !== null
            ? Tenant::query()
                ->where('id', $identifier)
                ->orWhere('slug', $identifier)
                ->get()
            : Tenant::query()
                ->whereIn('status', ['active', 'trial'])
                ->get();

        if ($tenants->isEmpty()) {
            $this->error($identifier === null ? 'No active tenants found.' : 'Tenant not found.');

            return self::FAILURE;
        }

        foreach ($tenants as $tenant) {
            $this->bootstrapper->bootstrapForTenant($tenant);
            $this->line("RBAC ready: {$tenant->slug} ({$tenant->getKey()})");
        }

        return self::SUCCESS;
    }
}
