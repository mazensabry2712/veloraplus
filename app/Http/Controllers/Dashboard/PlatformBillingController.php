<?php

namespace App\Http\Controllers\Dashboard;

use App\Application\Billing\PlatformBillingDashboardService;
use App\Domain\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\CancelSubscriptionRequest;
use App\Models\Subscription;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

final class PlatformBillingController extends Controller
{
    public function cancelSubscription(
        CancelSubscriptionRequest $request,
        Subscription $subscription,
        PlatformBillingDashboardService $billing,
        TenantContext $context,
    ): RedirectResponse {
        Gate::authorize('manageSubscription', $subscription);

        try {
            $billing->cancelAtPeriodEnd($subscription);
        } catch (DomainException $exception) {
            return back()->withErrors(['billing' => $exception->getMessage()]);
        }

        return to_route('dashboard')->with('status', 'Subscription cancellation scheduled successfully.');
    }
}
