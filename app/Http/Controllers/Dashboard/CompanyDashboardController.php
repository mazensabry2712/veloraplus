<?php

namespace App\Http\Controllers\Dashboard;

use App\Domain\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\TenantMembership;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class CompanyDashboardController extends Controller
{
    public function __invoke(Request $request, TenantContext $tenantContext): View
    {
        $tenant = $tenantContext->current();

        $membership = TenantMembership::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('account_id', $request->user()->getKey())
            ->where('status', 'active')
            ->firstOrFail();

        return view('dashboard.index', [
            'tenant' => $tenant,
            'membership' => $membership,
        ]);
    }
}
