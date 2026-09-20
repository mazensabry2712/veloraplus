<?php

namespace App\Http\Controllers\Dashboard;

use App\Application\Company\CompanyPreferencesManager;
use App\Domain\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\UpdateCompanyPreferencesRequest;
use App\Models\CompanySetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

final class CompanyPreferencesController extends Controller
{
    public function update(
        UpdateCompanyPreferencesRequest $request,
        TenantContext $context,
        CompanyPreferencesManager $manager,
    ): RedirectResponse {
        Gate::authorize('manage', CompanySetting::class);

        $tenant = $context->current();

        $manager->update($tenant, $request->validated());

        return to_route('dashboard.settings')->with('status', 'Company preferences updated successfully.');
    }
}
