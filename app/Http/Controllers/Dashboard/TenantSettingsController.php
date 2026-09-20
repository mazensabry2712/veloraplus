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

        $settings = $request->validated();

        if (isset($settings['seo'])) {
            $manager->updateSeo($settings['seo']);
        }

        if (isset($settings['social'])) {
            $manager->updateSocial($settings['social']);
        }

        if (isset($settings['tax'])) {
            $manager->updateTax($settings['tax']);
        }

        return to_route('dashboard')->with('status', 'Tenant settings updated successfully.');
    }
}
