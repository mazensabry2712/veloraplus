<?php

namespace App\Http\Controllers\Dashboard;

use App\Application\Billing\PlatformBillingDashboardService;
use App\Models\PlatformInvoice;
use App\Models\PlatformAccount;
use App\Domain\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\CancelSubscriptionRequest;
use App\Models\Subscription;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

final class PlatformBillingController extends Controller
{
    public function checkoutInvoice(
        \Illuminate\Http\Request $request,
        PlatformInvoice $invoice,
        PlatformBillingDashboardService $billing,
    ): RedirectResponse {
        Gate::authorize('manageInvoice', $invoice);

        /** @var PlatformAccount $account */
        $account = $request->user();

        $parts = preg_split('/\s+/', trim((string) $account->name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $firstName = $parts[0] ?? null;
        $lastName = count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : null;

        try {
            $result = $billing->checkoutInvoice($invoice, [
                'email' => $account->email,
                'reference' => $account->getKey(),
                'firstName' => $firstName,
                'lastName' => $lastName,
            ]);
        } catch (DomainException $exception) {
            return back()->withErrors(['billing' => $exception->getMessage()]);
        }

        return to_route('dashboard')
            ->with('status', 'Platform billing checkout created successfully.')
            ->with('platform_billing_checkout_url', $result['checkout']['checkout_url'] ?? null);
    }

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
