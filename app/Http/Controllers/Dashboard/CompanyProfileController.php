<?php

namespace App\Http\Controllers\Dashboard;

use App\Application\Company\CompanyProfileManager;
use App\Domain\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\UpdateCompanyProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

final class CompanyProfileController extends Controller
{
    public function show(TenantContext $tenantContext): View
    {
        $tenant = $tenantContext->current();

        Gate::authorize('company.view');

        return view('dashboard.company.profile', compact('tenant'));
    }


    public function update(
        UpdateCompanyProfileRequest $request,
        TenantContext $tenantContext,
        CompanyProfileManager $manager,
    ): RedirectResponse {
        $tenant = $tenantContext->current();

        Gate::authorize('update', $tenant);

        $manager->update($tenant, $request->validated());

        return to_route('dashboard')->with('status', 'Company profile updated successfully.');
    }
}
