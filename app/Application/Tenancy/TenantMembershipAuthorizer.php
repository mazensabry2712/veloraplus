<?php

namespace App\Application\Tenancy;

use App\Models\PlatformAccount;
use App\Models\Tenant;

final class TenantMembershipAuthorizer
{
    public function canAccess(PlatformAccount $account, Tenant $tenant): bool
    {
        return $tenant->memberships()
            ->where('account_id', $account->getKey())
            ->where('status', 'active')
            ->exists();
    }
}
