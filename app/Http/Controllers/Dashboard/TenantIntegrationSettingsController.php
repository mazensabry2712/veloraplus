<?php

namespace App\Http\Controllers\Dashboard;

use App\Application\Dashboard\TenantIntegrationSettingsService;
use App\Domain\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\UpdateTenantPaymentIntegrationRequest;
use App\Models\CompanySetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Gate;

final class TenantIntegrationSettingsController extends Controller
{
    public function show(
        TenantContext $context,
        TenantIntegrationSettingsService $service,
    ): View {
        $tenant = $context->current();

        Gate::authorize('viewAny', CompanySetting::class);

        return view('dashboard.settings', [
            'tenant' => $tenant,
            'payment' => $service->tenantPaymentSummary($tenant),
        ]);
    }

    public function updatePayment(
        UpdateTenantPaymentIntegrationRequest $request,
        TenantContext $context,
        TenantIntegrationSettingsService $service,
    ): RedirectResponse {
        Gate::authorize('manage', CompanySetting::class);

        $tenant = $context->current();

        $service->updateTenantPayment($tenant, $request->validated());

        return to_route('dashboard.settings')->with('status', 'Tenant payment integration updated successfully.');
    }
}
