<?php

namespace App\Http\Controllers\Dashboard;

use App\Application\Dashboard\DashboardContextService;
use App\Domain\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

final class CompanyDashboardController extends Controller
{
    public function __invoke(TenantContext $tenantContext, DashboardContextService $dashboardContext): View
    {
        $tenant = $tenantContext->current();

        return view('dashboard.index', [
            'tenant' => $tenant,
            'membership' => $dashboardContext->membership(),
        ]);
    }
}
