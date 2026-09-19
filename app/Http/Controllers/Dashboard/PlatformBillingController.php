<?php

namespace App\Http\Controllers\Dashboard;

use App\Application\Billing\PlatformBillingDashboardService;
use App\Domain\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\CancelSubscriptionRequest;
use App\Http\Requests\Dashboard\IssuePlatformCreditRequest;
use App\Http\Requests\Dashboard\RefundPlatformPaymentRequest;
use App\Http\Requests\Dashboard\VoidPlatformInvoiceRequest;
use App\Models\PlatformAccount;
use App\Models\PlatformInvoice;
use App\Models\PlatformPayment;
use App\Models\Subscription;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class PlatformBillingController extends Controller
{
    public function checkoutInvoice(
        Request $request,
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

    public function voidInvoice(
        VoidPlatformInvoiceRequest $request,
        PlatformInvoice $invoice,
        PlatformBillingDashboardService $billing,
    ): RedirectResponse {
        Gate::authorize('manageInvoice', $invoice);

        try {
            $billing->voidInvoice($invoice, (string) $request->validated('reason'));
        } catch (DomainException $exception) {
            return back()->withErrors(['billing' => $exception->getMessage()])->withInput();
        }

        return to_route('dashboard')->with('status', 'Platform invoice voided successfully.');
    }

    public function refundPayment(
        RefundPlatformPaymentRequest $request,
        PlatformPayment $payment,
        PlatformBillingDashboardService $billing,
    ): RedirectResponse {
        Gate::authorize('managePayment', $payment);

        try {
            /** @var PlatformAccount $account */
            $account = $request->user();
            $data = $request->validated();

            $refund = $billing->refundPayment(
                payment: $payment,
                amountMinor: (int) $data['amount_minor'],
                reason: (string) $data['reason'],
                idempotencyKey: $data['idempotency_key'] ?? null,
                initiatedByAccountId: (string) $account->getKey(),
            );
        } catch (DomainException $exception) {
            return back()->withErrors(['billing' => $exception->getMessage()])->withInput();
        }

        $message = $refund->status->value === 'succeeded'
            ? 'Platform payment refund processed successfully.'
            : 'Platform payment refund request submitted successfully.';

        return to_route('dashboard')->with('status', $message);
    }

    public function issueCredit(
        IssuePlatformCreditRequest $request,
        PlatformBillingDashboardService $billing,
        TenantContext $context,
    ): RedirectResponse {
        Gate::authorize('manage', Subscription::class);

        $tenant = $context->current();

        if ($tenant === null) {
            return back()->withErrors(['billing' => 'Tenant billing context is required.']);
        }

        try {
            $data = $request->validated();

            $billing->issueCredit(
                tenant: $tenant,
                amountMinor: (int) $data['amount_minor'],
                currency: (string) $data['currency'],
                source: $data['source'] ?? null,
                expiresAt: isset($data['expires_at'])
                    ? CarbonImmutable::parse($data['expires_at'])
                    : null,
            );
        } catch (DomainException $exception) {
            return back()->withErrors(['billing' => $exception->getMessage()])->withInput();
        }

        return to_route('dashboard')->with('status', 'Platform credit issued successfully.');
    }

    public function cancelSubscription(
        CancelSubscriptionRequest $request,
        Subscription $subscription,
        PlatformBillingDashboardService $billing,
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
