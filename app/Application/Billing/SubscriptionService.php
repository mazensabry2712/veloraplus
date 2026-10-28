<?php

namespace App\Application\Billing;

use App\Application\Catalog\CatalogDependencyResolver;
use App\Domain\Billing\InvoiceStatus;
use App\Domain\Billing\SubscriptionItemStatus;
use App\Domain\Billing\SubscriptionStatus;
use App\Models\Bundle;
use App\Models\Feature;
use App\Models\Module;
use App\Models\PlatformInvoice;
use App\Models\Subscription;
use App\Models\SubscriptionItem;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use DomainException;

final class SubscriptionService
{
    public function __construct(
        private readonly PricingEngine $pricing,
        private readonly InvoiceService $invoices,
        private readonly BillingEntitlementProjector $projector,
        private readonly BillingAuditLogger $audit,
        private readonly CatalogDependencyResolver $commercialDependencies,
    ) {
    }

    public function create(
        Tenant $tenant,
        array $items,
        string $billingCycle = 'monthly',
        int $trialDays = 14,
        ?string $currency = null,
        ?string $countryCode = null,
        int $taxBps = 0,
    ): Subscription {
        if ($trialDays < 0 || $trialDays > 90) {
            throw new DomainException('Trial days must be between 0 and 90.');
        }

        $now = CarbonImmutable::now();
        $trialEndsAt = $trialDays > 0 ? $now->addDays($trialDays) : null;
        $periodStart = $now;
        $periodEnd = $trialEndsAt ?? $this->advancePeriod($periodStart, $billingCycle);

        $subscription = $tenant->getConnection()->transaction(function () use (
            $tenant,
            $now,
            $trialEndsAt,
            $periodStart,
            $periodEnd,
            $billingCycle,
            $trialDays,
            $taxBps,
            $items,
            $currency,
            $countryCode,
        ): Subscription {
            $tenant = Tenant::query()
                ->lockForUpdate()
                ->findOrFail($tenant->getKey());

            // Resolve dependencies and calculate prices inside the same
            // transaction that persists the subscription and first invoice.
            // This prevents a catalog price/dependency change between quoting
            // and persistence from silently changing the commercial snapshot.
            $resolvedItems = $this->commercialDependencies->resolveBillableItems($items);
            $quote = $this->pricing->quote(
                $tenant,
                $billingCycle,
                $resolvedItems,
                $currency,
                $countryCode,
                $taxBps,
            );

            $existing = Subscription::query()
                ->where('tenant_id', $tenant->getKey())
                ->get()
                ->first(fn (Subscription $subscription) => $subscription->isOpen());

            if ($existing !== null) {
                throw new DomainException('Tenant already has an open subscription.');
            }

            $subscription = Subscription::query()->create([
                'tenant_id' => $tenant->getKey(),
                'status' => $trialEndsAt !== null
                    ? SubscriptionStatus::Trialing
                    : SubscriptionStatus::PendingPayment,
                'billing_cycle' => $billingCycle,
                'currency' => $quote['currency'],
                'country_code' => $quote['country_code'],
                'starts_at' => $now,
                'trial_ends_at' => $trialEndsAt,
                'current_period_start' => $periodStart,
                'current_period_end' => $periodEnd,
                'next_billed_at' => $trialEndsAt ?? $now,
                'cancel_at_period_end' => false,
                'subtotal_minor' => $quote['subtotal_minor'],
                'discount_minor' => $quote['discount_minor'],
                'tax_minor' => $quote['tax_minor'],
                'total_minor' => $quote['total_minor'],
                'metadata' => [
                    'trial_days' => $trialDays,
                    'tax_bps' => $taxBps,
                ],
            ]);

            foreach ($quote['lines'] as $line) {
                SubscriptionItem::query()->create([
                    'subscription_id' => $subscription->getKey(),
                    'item_type' => 'recurring',
                    'catalog_type' => $line['catalog_type'],
                    'catalog_key' => $line['catalog_key'],
                    'catalog_price_id' => $line['catalog_price_id'],
                    'quantity' => $line['quantity'],
                    'unit_amount_minor' => $line['unit_amount_minor'],
                    'discount_amount_minor' => $line['discount_minor'],
                    'tax_amount_minor' => $line['tax_minor'],
                    'line_total_minor' => $line['line_total_minor'],
                    'currency' => $quote['currency'],
                    'status' => $trialEndsAt !== null
                        ? SubscriptionItemStatus::Active
                        : SubscriptionItemStatus::Pending,
                    'starts_at' => $trialEndsAt !== null ? $now : null,
                    'ends_at' => $trialEndsAt !== null ? $trialEndsAt : null,
                    'activation_source' => $trialEndsAt !== null ? 'trial' : 'subscription',
                    'metadata' => $line['metadata'],
                ]);
            }

            $subscription = $subscription->fresh('items');

            // Keep the initial subscription and its first invoice atomic. If invoice
            // creation fails, the subscription must not be left pending without an invoice.
            if ($trialEndsAt === null) {
                $invoice = $this->invoices->createForSubscription($subscription, $subscription->items);

                if ($invoice->total_minor === 0) {
                    $paidAt = CarbonImmutable::now();
                    $this->invoices->markPaid($invoice, $paidAt);
                    $this->activateInvoiceItems($invoice->fresh('items'), $paidAt);
                    $subscription = $subscription->fresh('items');
                }
            }

            return $subscription;
        });

        if ($subscription->status === SubscriptionStatus::Trialing) {
            $this->projector->project($subscription);
            $this->audit->record($tenant, 'subscription.trial_started', $subscription, [
                'trial_ends_at' => $subscription->trial_ends_at?->toIso8601String(),
            ]);
        } elseif ($subscription->status === SubscriptionStatus::PendingPayment) {
            $this->audit->record($tenant, 'subscription.created_pending_payment', $subscription);
        } else {
            $this->audit->record($tenant, 'subscription.created_without_payment', $subscription);
        }

        return $subscription->refresh();
    }

    /**
     * Preview an upgrade using the exact commercial rules used by requestUpgrade().
     *
     * This method is read-only: it creates no subscription items, invoice, or payment.
     *
     * @param array<int, array{item: \\App\\Models\\Module|Feature|\\App\\Models\\Bundle, quantity?: int}> $items
     * @return array<string, mixed>
     */
    public function previewUpgrade(
        Subscription $subscription,
        array $items,
        int $taxBps = 0,
    ): array {
        if (! in_array($subscription->status, [
            SubscriptionStatus::Active,
            SubscriptionStatus::Grace,
        ], true)) {
            throw new DomainException('Only an active or grace subscription can be upgraded.');
        }

        $subscription->loadMissing('items');
        $existingItems = $subscription->items
            ->whereIn('status', [SubscriptionItemStatus::Active, SubscriptionItemStatus::Pending]);

        $existing = $existingItems
            ->mapWithKeys(fn (SubscriptionItem $item) => [$item->catalog_type.':'.$item->catalog_key => true])
            ->all();
        $coveredQuantities = $existingItems
            ->groupBy(fn (SubscriptionItem $item) => $item->catalog_type.':'.$item->catalog_key)
            ->map(fn ($items) => (int) $items->sum('quantity'))
            ->all();

        // An active Module or Bundle also covers the capabilities it grants.
        // Include that implicit coverage at the billing boundary, not only in
        // Marketplace UI checks, so direct upgrade requests cannot double-bill.
        foreach ($existingItems as $existingItem) {
            foreach ($this->coveredCatalogItems($existingItem) as $coveredItem) {
                $key = $coveredItem->getMorphClass().':'.$coveredItem->key;
                $existing[$key] = true;
                $coveredQuantities[$key] = max(
                    (int) ($coveredQuantities[$key] ?? 0),
                    (int) $existingItem->quantity,
                );
            }
        }

        $items = $this->commercialDependencies->resolveBillableItems($items, $coveredQuantities);

        $quote = $this->pricing->quote(
            $subscription->tenant,
            $subscription->billing_cycle,
            $items,
            $subscription->currency,
            $subscription->country_code,
            $taxBps,
        );

        foreach ($quote['lines'] as $line) {
            $key = $line['catalog_type'].':'.$line['catalog_key'];
            if (isset($existing[$key])) {
                throw new DomainException("Catalog item {$line['catalog_key']} is already part of the subscription.");
            }
        }

        return $quote;
    }

    public function requestUpgrade(
        Subscription $subscription,
        array $items,
        int $taxBps = 0,
        ?\Closure $quoteValidator = null,
    ): PlatformInvoice {
        // Lock and re-quote inside the transaction so concurrent requests cannot
        // both pass the "not already subscribed" check using stale item state.
        $invoice = $subscription->getConnection()->transaction(function () use (
            $subscription,
            $items,
            $taxBps,
            $quoteValidator,
        ): PlatformInvoice {
            $lockedSubscription = Subscription::query()
                ->whereKey($subscription->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $quote = $this->previewUpgrade($lockedSubscription, $items, $taxBps);

            if ($quoteValidator !== null) {
                $quoteValidator($lockedSubscription, $quote);
            }

            $newItems = [];

            foreach ($quote['lines'] as $line) {
                $newItems[] = SubscriptionItem::query()->create([
                    'subscription_id' => $lockedSubscription->getKey(),
                    'item_type' => 'recurring',
                    'catalog_type' => $line['catalog_type'],
                    'catalog_key' => $line['catalog_key'],
                    'catalog_price_id' => $line['catalog_price_id'],
                    'quantity' => $line['quantity'],
                    'unit_amount_minor' => $line['unit_amount_minor'],
                    'discount_amount_minor' => $line['discount_minor'],
                    'tax_amount_minor' => $line['tax_minor'],
                    'line_total_minor' => $line['line_total_minor'],
                    'currency' => $quote['currency'],
                    'status' => SubscriptionItemStatus::Pending,
                    'starts_at' => null,
                    'ends_at' => null,
                    'activation_source' => 'subscription',
                    'metadata' => array_merge($line['metadata'], ['upgrade' => true]),
                ]);
            }

            $invoice = $this->invoices->createForSubscription($lockedSubscription, $newItems, null, [
                'type' => 'upgrade',
            ]);

            if ($invoice->total_minor === 0) {
                $paidAt = CarbonImmutable::now();
                $this->invoices->markPaid($invoice, $paidAt);
                $this->activateInvoiceItems($invoice->fresh('items'), $paidAt);

                return $invoice->fresh('items');
            }

            return $invoice;
        });

        $this->audit->record(
            $subscription->tenant,
            $invoice->status === InvoiceStatus::Paid
                ? 'subscription.upgrade_activated_without_payment'
                : 'subscription.upgrade_requested',
            $subscription,
            ['invoice_id' => $invoice->getKey()],
        );

        return $invoice;
    }

    public function scheduleDowngrade(
        Subscription $subscription,
        SubscriptionItem $item,
    ): SubscriptionItem {
        $scheduledItem = $subscription->getConnection()->transaction(function () use (
            $subscription,
            $item,
        ): SubscriptionItem {
            $lockedSubscription = Subscription::query()
                ->lockForUpdate()
                ->with('items')
                ->findOrFail($subscription->getKey());

            $lockedItem = SubscriptionItem::query()
                ->where('subscription_id', $lockedSubscription->getKey())
                ->lockForUpdate()
                ->findOrFail($item->getKey());

            if ($lockedItem->subscription_id !== $lockedSubscription->getKey()) {
                throw new DomainException('Subscription item does not belong to the subscription.');
            }

            if ($lockedSubscription->status !== SubscriptionStatus::Active) {
                throw new DomainException('Only an active subscription can schedule a downgrade.');
            }

            if ($lockedItem->status !== SubscriptionItemStatus::Active) {
                throw new DomainException('Only an active subscription item can be scheduled for removal.');
            }

            if ($lockedItem->catalog_type === 'feature') {
                $feature = Feature::query()->where('key', $lockedItem->catalog_key)->first();
                if ($feature?->is_required) {
                    throw new DomainException('Required Features cannot be removed independently.');
                }
            }

            foreach ($lockedSubscription->items as $otherItem) {
                if (
                    $otherItem->getKey() === $lockedItem->getKey()
                    || ! in_array($otherItem->status, [
                        SubscriptionItemStatus::Active,
                        SubscriptionItemStatus::Pending,
                    ], true)
                ) {
                    continue;
                }

                foreach ($this->dependencyRootsFor($otherItem) as $root) {
                    foreach ($this->commercialDependencies->resolve([$root]) as $dependency) {
                        if (
                            ! $dependency->is($root)
                            && $dependency->getMorphClass() === $lockedItem->catalog_type
                            && $dependency->key === $lockedItem->catalog_key
                        ) {
                            throw new DomainException(
                                "Catalog item {$lockedItem->catalog_key} is required by another active or pending subscription item."
                            );
                        }
                    }
                }
            }

            $lockedItem->update([
                'status' => SubscriptionItemStatus::Scheduled,
                'ends_at' => $lockedSubscription->current_period_end,
            ]);

            $lockedSubscription->refresh()->load('items');
            $this->projector->project($lockedSubscription);
            $this->audit->record($lockedSubscription->tenant, 'subscription.item_downgrade_scheduled', $lockedItem, [
                'ends_at' => $lockedItem->ends_at?->toIso8601String(),
            ]);

            return $lockedItem->refresh();
        });

        return $scheduledItem->refresh();
    }

    /**
     * @return array<int, Module|Feature>
     */
    private function dependencyRootsFor(SubscriptionItem $item): array
    {
        return match ($item->catalog_type) {
            'module' => $this->moduleDependencyRoots(
                Module::query()->where('key', $item->catalog_key)->first(),
            ),
            'feature' => array_values(array_filter([
                Feature::query()->where('key', $item->catalog_key)->first(),
            ])),
            'bundle' => $this->bundleDependencyRoots($item->catalog_key),
            default => [],
        };
    }

    /**
     * @return array<int, Module|Feature>
     */
    private function moduleDependencyRoots(?Module $module): array
    {
        if ($module === null) {
            return [];
        }

        $module->loadMissing('features');

        return [
            $module,
            ...$module->features
                ->filter(fn (Feature $feature) => $feature->status === 'active')
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<int, Module|Feature>
     */
    private function bundleDependencyRoots(string $bundleKey): array
    {
        $bundle = Bundle::query()
            ->with(['modules.features', 'features'])
            ->where('key', $bundleKey)
            ->first();

        if ($bundle === null) {
            return [];
        }

        $roots = [];

        foreach ($bundle->modules as $module) {
            $roots = [...$roots, ...$this->moduleDependencyRoots($module)];
        }

        return [...$roots, ...$bundle->features->all()];
    }

    public function cancelAtPeriodEnd(Subscription $subscription): Subscription
    {
        if (! $subscription->isOpen()) {
            throw new DomainException('Only an open subscription can be cancelled.');
        }

        $subscription->update(['cancel_at_period_end' => true]);

        $this->audit->record($subscription->tenant, 'subscription.cancellation_scheduled', $subscription);

        return $subscription->refresh();
    }

    public function processPeriodEnd(
        Subscription $subscription,
        ?CarbonImmutable $at = null,
    ): ?PlatformInvoice {
        $at ??= CarbonImmutable::now();

        return $subscription->getConnection()->transaction(function () use ($subscription, $at): ?PlatformInvoice {
            $subscription = Subscription::query()
                ->lockForUpdate()
                ->with('items')
                ->findOrFail($subscription->getKey());

            if ($subscription->current_period_end > $at) {
                throw new DomainException('Subscription period has not ended yet.');
            }

            if ($subscription->cancel_at_period_end) {
                $subscription->update([
                    'status' => SubscriptionStatus::Cancelled,
                    'cancelled_at' => $at,
                ]);

                $subscription->items->each(fn (SubscriptionItem $item) => $item->update([
                    'status' => SubscriptionItemStatus::Inactive,
                    'ends_at' => $at,
                ]));

                $this->projector->project($subscription->fresh('items'));
                $this->audit->record($subscription->tenant, 'subscription.cancelled', $subscription);

                return null;
            }

            $renewalItems = [];

            foreach ($subscription->items as $item) {
                if ($item->status === SubscriptionItemStatus::Scheduled) {
                    $item->update([
                        'status' => SubscriptionItemStatus::Inactive,
                        'ends_at' => $at,
                    ]);
                    continue;
                }

                if ($item->status === SubscriptionItemStatus::Active) {
                    $item->update([
                        'status' => SubscriptionItemStatus::Pending,
                        'starts_at' => null,
                        'ends_at' => null,
                    ]);
                    $renewalItems[] = $item->fresh();
                }
            }

            $oldEnd = $subscription->current_period_end;
            $newEnd = $this->advancePeriod($oldEnd, $subscription->billing_cycle);

            $subscription->update([
                'status' => SubscriptionStatus::PendingPayment,
                'current_period_start' => $oldEnd,
                'current_period_end' => $newEnd,
                'next_billed_at' => $oldEnd,
            ]);

            $subscription->refresh();
            $this->projector->project($subscription);

            if ($renewalItems === []) {
                $subscription->update([
                    'status' => SubscriptionStatus::Expired,
                    'next_billed_at' => null,
                ]);

                $this->projector->project($subscription->fresh('items'));
                $this->audit->record($subscription->tenant, 'subscription.expired_no_items', $subscription);

                return null;
            }

            $invoice = $this->invoices->createForSubscription($subscription, $renewalItems, null, [
                'type' => 'renewal',
            ]);

            if ($invoice->total_minor === 0) {
                $paidAt = CarbonImmutable::now();
                $this->invoices->markPaid($invoice, $paidAt);
                $this->activateInvoiceItems($invoice->fresh('items'), $paidAt);
                $subscription->refresh();
                $this->audit->record($subscription->tenant, 'subscription.renewal_paid_without_payment', $subscription, [
                    'invoice_id' => $invoice->getKey(),
                ]);

                return $invoice->fresh('items');
            }

            $this->audit->record($subscription->tenant, 'subscription.renewal_pending_payment', $subscription, [
                'invoice_id' => $invoice->getKey(),
            ]);

            return $invoice;
        });
    }

    public function activateInvoiceItems(
        PlatformInvoice $invoice,
        CarbonImmutable $at,
    ): Subscription {
        $invoice = PlatformInvoice::query()->with('items')->findOrFail($invoice->getKey());

        if ($invoice->status->value !== 'paid') {
            throw new DomainException('Subscription items can only activate from a paid invoice.');
        }

        $subscription = Subscription::query()
            ->lockForUpdate()
            ->with('items')
            ->findOrFail($invoice->subscription_id);

        $invoiceItemIds = $invoice->items()->pluck('subscription_item_id')->filter()->all();

        foreach ($subscription->items as $item) {
            if (! in_array($item->getKey(), $invoiceItemIds, true)) {
                continue;
            }

            $item->update([
                'status' => SubscriptionItemStatus::Active,
                'starts_at' => $at,
                'ends_at' => $subscription->current_period_end,
                'activation_source' => $item->activation_source ?: 'subscription',
            ]);
        }

        if ($subscription->status === SubscriptionStatus::PendingPayment) {
            $subscription->update([
                'status' => SubscriptionStatus::Active,
                'current_period_start' => $subscription->current_period_start < $at
                    ? $subscription->current_period_start
                    : $at,
                'next_billed_at' => $subscription->current_period_end,
            ]);
        }

        $subscription->refresh();
        $this->projector->project($subscription);
        $this->audit->record($subscription->tenant, 'subscription.items_activated', $subscription, [
            'invoice_id' => $invoice->getKey(),
            'paid_at' => $at->toIso8601String(),
        ]);

        return $subscription;
    }

    /**
     * @return array<int, Module|Feature|Bundle>
     */
    private function coveredCatalogItems(SubscriptionItem $subscriptionItem): array
    {
        $catalogItem = match ($subscriptionItem->catalog_type) {
            'module' => Module::query()->where('key', $subscriptionItem->catalog_key)->first(),
            'feature' => Feature::query()->where('key', $subscriptionItem->catalog_key)->first(),
            'bundle' => Bundle::query()
                ->with(['modules.features', 'features'])
                ->where('key', $subscriptionItem->catalog_key)
                ->first(),
            default => null,
        };

        if ($catalogItem === null) {
            return [];
        }

        $covered = [];
        $queue = [];

        if ($catalogItem instanceof Bundle) {
            $covered[] = $catalogItem;

            foreach ($catalogItem->modules as $module) {
                $queue[] = $module;
            }

            foreach ($catalogItem->features as $feature) {
                $queue[] = $feature;
            }
        } else {
            $queue[] = $catalogItem;
        }

        $visited = [];

        while ($queue !== []) {
            /** @var Module|Feature $item */
            $item = array_shift($queue);
            $key = $item->getMorphClass().':'.$item->key;

            if (isset($visited[$key])) {
                continue;
            }

            $visited[$key] = true;
            $covered[] = $item;

            if ($item instanceof Module) {
                $item->loadMissing('features');

                foreach ($item->features as $feature) {
                    if ($feature->status === 'active') {
                        $queue[] = $feature;
                    }
                }
            }

            foreach ($this->commercialDependencies->resolve([$item]) as $dependency) {
                if (! $dependency->is($item)) {
                    $queue[] = $dependency;
                }
            }
        }

        $unique = [];

        foreach ($covered as $item) {
            $unique[$item->getMorphClass().':'.$item->key] = $item;
        }

        return array_values($unique);
    }

    private function advancePeriod(CarbonImmutable $from, string $billingCycle): CarbonImmutable
    {
        return strtolower($billingCycle) === 'yearly'
            ? $from->addYear()
            : $from->addMonth();
    }
}
