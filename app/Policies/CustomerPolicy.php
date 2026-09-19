<?php

namespace App\Policies;

use App\Domain\Tenancy\TenantContext;
use App\Models\Customer;
use App\Models\PlatformAccount;

class CustomerPolicy
{
    public function viewAny(PlatformAccount $account): bool
    {
        return $this->inTenantContext() && $account->can('customers.view');
    }

    public function view(PlatformAccount $account, Customer $customer): bool
    {
        return $this->inCurrentTenantModel($customer) && $account->can('customers.view');
    }

    public function create(PlatformAccount $account): bool
    {
        return $this->inTenantContext() && $account->can('customers.manage');
    }

    public function update(PlatformAccount $account, Customer $customer): bool
    {
        return $this->inCurrentTenantModel($customer) && $account->can('customers.manage');
    }

    public function delete(PlatformAccount $account, Customer $customer): bool
    {
        return $this->inCurrentTenantModel($customer) && $account->can('customers.manage');
    }

    private function inTenantContext(): bool
    {
        return app(TenantContext::class)->check();
    }

    private function inCurrentTenantModel(Customer $customer): bool
    {
        return $this->inTenantContext()
            && $customer->getConnectionName() === 'tenant';
    }
}
