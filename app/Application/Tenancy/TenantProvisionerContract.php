<?php

namespace App\Application\Tenancy;

use App\Models\Tenant;

interface TenantProvisionerContract
{
    public function provision(Tenant $tenant): Tenant;
}
