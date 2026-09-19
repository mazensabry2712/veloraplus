<?php

namespace App\Http\Controllers\Dashboard;

use App\Application\Company\TenantSettingsManager;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\UpdateTenantSettingsRequest;
use App\Models\CompanySetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

final class TenantSettingsController extends Controller
{
    public function update(
        UpdateTenantSettingsRequest $request,
        TenantSettingsManager $manager,
    ): RedirectResponse {
        Gate::authorize('manage', CompanySetting::class);

        $manager->updateSeo($request->validated('seo'));

        return to_route('dashboard')->with('status', 'Tenant settings updated successfully.');
    }
}
