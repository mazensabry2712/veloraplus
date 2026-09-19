<?php

namespace App\Policies;

use App\Domain\Tenancy\TenantContext;
use App\Models\Location;
use App\Models\PlatformAccount;

class LocationPolicy
{
    public function viewAny(PlatformAccount $account): bool
    {
        return $this->inTenantContext() && $account->can('locations.view');
    }

    public function view(PlatformAccount $account, Location $location): bool
    {
        return $this->inCurrentLocationContext($location) && $account->can('locations.view');
    }

    public function create(PlatformAccount $account): bool
    {
        return $this->inTenantContext() && $account->can('locations.manage');
    }

    public function update(PlatformAccount $account, Location $location): bool
    {
        return $this->inCurrentLocationContext($location) && $account->can('locations.manage');
    }

    public function delete(PlatformAccount $account, Location $location): bool
    {
        return $this->inCurrentLocationContext($location) && $account->can('locations.manage');
    }

    private function inTenantContext(): bool
    {
        return app(TenantContext::class)->check();
    }

    private function inCurrentLocationContext(Location $location): bool
    {
        return app(TenantContext::class)->check()
            && $location->getConnectionName() === 'tenant';
    }
}
