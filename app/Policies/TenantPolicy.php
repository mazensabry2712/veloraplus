<?php

namespace App\Policies;

use App\Domain\Tenancy\TenantContext;
use App\Models\PlatformAccount;
use App\Models\Tenant;

class TenantPolicy
{
    public function view(PlatformAccount $account, Tenant $tenant): bool
    {
        return $this->inCurrentTenant($tenant)
            && $account->can('company.view');
    }

    public function update(PlatformAccount $account, Tenant $tenant): bool
    {
        return $this->inCurrentTenant($tenant)
            && $account->can('company.update');
    }

    private function inCurrentTenant(Tenant $tenant): bool
    {
        $current = app(TenantContext::class)->get();

        return $current !== null && $current->is($tenant);
    }
}
