<?php

namespace App\Policies;

use App\Domain\Tenancy\TenantContext;
use App\Models\CompanySetting;
use App\Models\PlatformAccount;

class CompanySettingPolicy
{
    public function viewAny(PlatformAccount $account): bool
    {
        return $this->inTenantContext() && $account->can('settings.view');
    }

    public function manage(PlatformAccount $account): bool
    {
        return $this->inTenantContext() && $account->can('settings.manage');
    }

    private function inTenantContext(): bool
    {
        return app(TenantContext::class)->check();
    }
}
