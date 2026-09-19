<?php

namespace App\Policies;

use App\Domain\Tenancy\TenantContext;
use App\Models\PlatformAccount;
use App\Models\TenantMembership;

class TenantMembershipPolicy
{
    public function viewAny(PlatformAccount $account): bool
    {
        return $this->inTenantContext() && $account->can('members.view');
    }

    public function view(PlatformAccount $account, TenantMembership $membership): bool
    {
        return $this->belongsToCurrentTenant($membership) && $account->can('members.view');
    }

    public function create(PlatformAccount $account): bool
    {
        return $this->inTenantContext() && $account->can('members.manage');
    }

    public function update(PlatformAccount $account, TenantMembership $membership): bool
    {
        return $this->belongsToCurrentTenant($membership) && $account->can('members.manage');
    }

    public function delete(PlatformAccount $account, TenantMembership $membership): bool
    {
        return $this->belongsToCurrentTenant($membership) && $account->can('members.manage');
    }

    private function inTenantContext(): bool
    {
        return app(TenantContext::class)->check();
    }

    private function belongsToCurrentTenant(TenantMembership $membership): bool
    {
        $tenant = app(TenantContext::class)->get();

        return $tenant !== null && $membership->tenant_id === $tenant->getKey();
    }
}
