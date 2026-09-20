<?php

namespace App\Http\Controllers\Dashboard;

use App\Application\Company\CompanyBrandingManager;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\UpdateCompanyBrandingRequest;
use App\Models\CompanySetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

final class CompanyBrandingController extends Controller
{
    public function update(
        UpdateCompanyBrandingRequest $request,
        CompanyBrandingManager $manager,
    ): RedirectResponse {
        Gate::authorize('manage', CompanySetting::class);

        $manager->update($request->validated());

        return to_route('dashboard')->with('status', 'Company branding updated successfully.');
    }
}
