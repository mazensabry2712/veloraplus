<?php

namespace App\Application\Billing;

use App\Application\Payments\PlatformPaymentCheckoutService;
use App\Domain\Billing\InvoiceStatus;
use App\Domain\Billing\PaymentStatus;
use App\Domain\Tenancy\TenantContext;
use App\Models\PlatformCredit;
use App\Models\PlatformInvoice;
use App\Models\PlatformPayment;
use App\Models\Bundle;
use App\Models\Feature;
use App\Models\Module;
use App\Models\PlatformRefund;
use App\Models\Subscription;
use App\Models\SubscriptionItem;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Collection;

final class PlatformBillingDashboardService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly SubscriptionService $subscriptions,
        private readonly PaymentService $payments,
        private readonly PlatformPaymentCheckoutService $checkout,
        private readonly InvoiceService $invoices,
        private readonly PlatformBillingRefundManager $refundManager,
        private readonly CreditService $credits,
    ) {}

    /**
     * @return array{
     *     subscription: ?Subscription,
     *     invoices: Collection<int, PlatformInvoice>,
     *     payments: Collection<int, PlatformPayment>,
     *     refunds: Collection<int, PlatformRefund>,
     *     credits: Collection<int, PlatformCredit>
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
            'refunds' => PlatformRefund::query()
                ->where('tenant_id', $tenant->getKey())
                ->with('payment.invoice')
                ->latest()
                ->limit($limit)
                ->get(),
            'credits' => PlatformCredit::query()
                ->where('tenant_id', $tenant->getKey())
                ->latest()
                ->limit($limit)
                ->get(),
        ];
    }

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

    public function requestUpgrade(Subscription $subscription, array $items): PlatformInvoice
    {
        $tenant = $this->tenantContext->current();

        $this->assertCurrentTenant($tenant);
        $this->assertSubscriptionBelongsToTenant($subscription, $tenant);

        $subscription->loadMissing('items');

        $resolvedItems = collect($items)
            ->map(function (array $item): array {
                $type = strtolower(trim((string) ($item['catalog_type'] ?? '')));
                $key = strtolower(trim((string) ($item['catalog_key'] ?? '')));
                $quantity = (int) ($item['quantity'] ?? 1);

                $class = match ($type) {
                    'module' => Module::class,
                    'feature' => Feature::class,
                    'bundle' => Bundle::class,
                    default => throw new DomainException('Unsupported subscription catalog item type.'),
                };

                $catalogItem = $class::query()->where('key', $key)->first();

                if ($catalogItem === null) {
                    throw new DomainException("Catalog item {$key} was not found.");
                }

                return [
                    'item' => $catalogItem,
                    'quantity' => $quantity,
                ];
            })
            ->all();

        return $this->subscriptions->requestUpgrade(
            $subscription,
            $resolvedItems,
            (int) ($subscription->metadata['tax_bps'] ?? 0),
        );
    }

    public function scheduleDowngrade(Subscription $subscription, SubscriptionItem $item): SubscriptionItem
    {
        $tenant = $this->tenantContext->current();

        $this->assertCurrentTenant($tenant);
        $this->assertSubscriptionBelongsToTenant($subscription, $tenant);

        return $this->subscriptions->scheduleDowngrade($subscription, $item);
    }

    public function cancelAtPeriodEnd(Subscription $subscription): Subscription
    {
        $tenant = $this->tenantContext->current();

        $this->assertCurrentTenant($tenant);
        $this->assertSubscriptionBelongsToTenant($subscription, $tenant);

        return $this->subscriptions->cancelAtPeriodEnd($subscription);
    }

    public function voidInvoice(PlatformInvoice $invoice, string $reason): PlatformInvoice
    {
        $tenant = $this->tenantContext->current();

        $this->assertCurrentTenant($tenant);

        if ((string) $invoice->tenant_id !== (string) $tenant->getKey()) {
            throw new DomainException('Invoice does not belong to the current tenant.');
        }

        return $this->invoices->void($invoice, $reason);
    }

    public function refundPayment(
        PlatformPayment $payment,
        int $amountMinor,
        string $reason,
        ?string $idempotencyKey = null,
        ?string $initiatedByAccountId = null,
    ): PlatformRefund {
        $tenant = $this->tenantContext->current();

        $this->assertCurrentTenant($tenant);

        return $this->refundManager->request(
            payment: $payment,
            amountMinor: $amountMinor,
            reason: $reason,
            idempotencyKey: $idempotencyKey,
            initiatedByAccountId: $initiatedByAccountId,
        );
    }

    public function issueCredit(
        Tenant $tenant,
        int $amountMinor,
        string $currency,
        ?string $source = null,
        ?\DateTimeInterface $expiresAt = null,
    ): PlatformCredit {
        $this->assertCurrentTenant($tenant);

        return $this->credits->issue(
            tenant: $tenant,
            amountMinor: $amountMinor,
            currency: $currency,
            source: $source,
            expiresAt: $expiresAt !== null
                ? CarbonImmutable::instance($expiresAt)
                : null,
        );
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
