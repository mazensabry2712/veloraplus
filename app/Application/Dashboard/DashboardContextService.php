<?php

namespace App\Application\Dashboard;

use App\Domain\Tenancy\TenantContext;
use App\Models\TenantMembership;
use RuntimeException;

final class DashboardContextService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    public function membership(): TenantMembership
    {
        $tenant = $this->tenantContext->current();
        $account = auth()->user();

        if ($tenant === null || $account === null) {
            throw new RuntimeException('Dashboard context requires an authenticated tenant member.');
        }

        return TenantMembership::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('account_id', $account->getKey())
            ->where('status', 'active')
            ->firstOrFail();
    }
}
