<?php

namespace App\Application\Billing;

use App\Application\Payments\PlatformPaymentCheckoutService;
use App\Domain\Billing\InvoiceStatus;
use App\Domain\Billing\PaymentStatus;
use App\Domain\Tenancy\TenantContext;
use App\Models\PlatformInvoice;
use App\Models\PlatformPayment;
use App\Models\Subscription;
use App\Models\Tenant;
use DomainException;
use Illuminate\Support\Collection;

final class PlatformBillingDashboardService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly SubscriptionService $subscriptions,
        private readonly PaymentService $payments,
        private readonly PlatformPaymentCheckoutService $checkout,
    ) {}

    /**
     * @return array{
     *     subscription: ?Subscription,
     *     invoices: Collection<int, PlatformInvoice>,
     *     payments: Collection<int, PlatformPayment>
     * }
     */
    public function overview(Tenant $tenant, int $limit = 12): array
    {
        $this->assertCurrentTenant($tenant);

        $limit = max(1, min($limit, 50));

        return [
            'subscription' => Subscription::query()
                ->where('tenant_id', $tenant->getKey())
                ->orderByDesc('starts_at')
                ->first(),
            'invoices' => PlatformInvoice::query()
                ->where('tenant_id', $tenant->getKey())
                ->with(['items', 'subscription'])
                ->latest('issued_at')
                ->limit($limit)
                ->get(),
            'payments' => PlatformPayment::query()
                ->where('tenant_id', $tenant->getKey())
                ->with('invoice')
                ->latest()
                ->limit($limit)
                ->get(),
        ];
    }

    /**
     * @param array<string, mixed> $customer
     * @return array{payment: PlatformPayment, checkout: array<string, mixed>}
     */
    public function checkoutInvoice(
        PlatformInvoice $invoice,
        array $customer = [],
    ): array {
        $tenant = $this->tenantContext->current();

        $this->assertCurrentTenant($tenant);

        if ($invoice->tenant_id !== $tenant->getKey()) {
            throw new DomainException('Invoice does not belong to the current tenant.');
        }

        if ($invoice->status !== InvoiceStatus::Open) {
            throw new DomainException('Only an open invoice can be checked out.');
        }

        $payment = PlatformPayment::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('invoice_id', $invoice->getKey())
            ->where('status', PaymentStatus::Pending->value)
            ->latest()
            ->first();

        if ($payment === null) {
            $payment = $this->payments->createPending($invoice);
        }

        $checkout = $this->checkout->create(
            $payment,
            $customer,
        );

        return [
            'payment' => $payment->fresh(),
            'checkout' => $checkout,
        ];
    }

    public function cancelAtPeriodEnd(Subscription $subscription): Subscription
    {
        $tenant = $this->tenantContext->current();

        $this->assertCurrentTenant($tenant);
        $this->assertSubscriptionBelongsToTenant($subscription, $tenant);

        return $this->subscriptions->cancelAtPeriodEnd($subscription);
    }

    private function assertCurrentTenant(?Tenant $tenant): void
    {
        if ($tenant === null) {
            throw new DomainException('Tenant billing context is required.');
        }

        if (! $this->tenantContext->check() || $tenant->getKey() !== $this->tenantContext->current()?->getKey()) {
            throw new DomainException('Tenant billing context does not match the requested tenant.');
        }
    }

    private function assertSubscriptionBelongsToTenant(Subscription $subscription, Tenant $tenant): void
    {
        if ($subscription->tenant_id !== $tenant->getKey()) {
            throw new DomainException('Subscription does not belong to the current tenant.');
        }
    }
}
