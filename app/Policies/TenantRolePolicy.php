<?php

namespace App\Policies;

use App\Domain\Tenancy\TenantContext;
use App\Models\PlatformAccount;
use App\Models\Role;

class TenantRolePolicy
{
    public function viewAny(PlatformAccount $account): bool
    {
        return $this->inTenantContext() && $account->can('members.view');
    }

    public function view(PlatformAccount $account, Role $role): bool
    {
        return $this->belongsToCurrentTenant($role) && $account->can('members.view');
    }

    public function create(PlatformAccount $account): bool
    {
        return $this->inTenantContext() && $account->can('members.manage');
    }

    public function update(PlatformAccount $account, Role $role): bool
    {
        return $this->belongsToCurrentTenant($role) && $account->can('members.manage');
    }

    public function delete(PlatformAccount $account, Role $role): bool
    {
        return $this->belongsToCurrentTenant($role) && $account->can('members.manage');
    }

    private function inTenantContext(): bool
    {
        return app(TenantContext::class)->check();
    }

    private function belongsToCurrentTenant(Role $role): bool
    {
        $tenant = app(TenantContext::class)->get();

        return $tenant !== null && $role->tenant_id === $tenant->getKey();
    }
}
