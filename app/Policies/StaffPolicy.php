<?php

namespace App\Policies;

use App\Domain\Tenancy\TenantContext;
use App\Models\PlatformAccount;
use App\Models\Staff;

class StaffPolicy
{
    public function viewAny(PlatformAccount $account): bool
    {
        return $this->inTenantContext() && $account->can('staff.view');
    }

    public function view(PlatformAccount $account, Staff $staff): bool
    {
        return $this->inCurrentTenantModel($staff) && $account->can('staff.view');
    }

    public function create(PlatformAccount $account): bool
    {
        return $this->inTenantContext() && $account->can('staff.manage');
    }

    public function update(PlatformAccount $account, Staff $staff): bool
    {
        return $this->inCurrentTenantModel($staff) && $account->can('staff.manage');
    }

    public function delete(PlatformAccount $account, Staff $staff): bool
    {
        return $this->inCurrentTenantModel($staff) && $account->can('staff.manage');
    }

    public function manageAvailability(PlatformAccount $account, Staff $staff): bool
    {
        return $this->inCurrentTenantModel($staff) && $account->can('booking.availability.manage');
    }

    private function inTenantContext(): bool
    {
        return app(TenantContext::class)->check();
    }

    private function inCurrentTenantModel(Staff $staff): bool
    {
        return $this->inTenantContext()
            && $staff->getConnectionName() === 'tenant';
    }
}
