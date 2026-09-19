<?php

namespace App\Application\Billing;

use App\Domain\Billing\SubscriptionItemStatus;
use App\Domain\Billing\SubscriptionStatus;
use App\Models\Feature;
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

        $quote = $this->pricing->quote(
            $tenant,
            $billingCycle,
            $items,
            $currency,
            $countryCode,
            $taxBps,
        );

        $now = CarbonImmutable::now();
        $trialEndsAt = $trialDays > 0 ? $now->addDays($trialDays) : null;
        $periodStart = $now;
        $periodEnd = $trialEndsAt ?? $this->advancePeriod($periodStart, $billingCycle);

        $subscription = $tenant->getConnection()->transaction(function () use (
            $tenant,
            $quote,
            $now,
            $trialEndsAt,
            $periodStart,
            $periodEnd,
            $billingCycle,
            $trialDays,
            $taxBps,
        ): Subscription {
            $tenant = Tenant::query()
                ->lockForUpdate()
                ->findOrFail($tenant->getKey());

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

            return $subscription->fresh('items');
        });

        if ($subscription->status === SubscriptionStatus::Trialing) {
            $this->projector->project($subscription);
            $this->audit->record($tenant, 'subscription.trial_started', $subscription, [
                'trial_ends_at' => $subscription->trial_ends_at?->toIso8601String(),
            ]);
        } else {
            $this->invoices->createForSubscription(
                $subscription,
                $subscription->items,
            );
            $this->audit->record($tenant, 'subscription.created_pending_payment', $subscription);
        }

        return $subscription->refresh();
    }

    public function requestUpgrade(
        Subscription $subscription,
        array $items,
        int $taxBps = 0,
    ): PlatformInvoice {
        if (! in_array($subscription->status, [
            SubscriptionStatus::Active,
            SubscriptionStatus::Grace,
        ], true)) {
            throw new DomainException('Only an active or grace subscription can be upgraded.');
        }

        $subscription->loadMissing('items');
        $existing = $subscription->items
            ->whereIn('status', [SubscriptionItemStatus::Active, SubscriptionItemStatus::Pending])
            ->mapWithKeys(fn (SubscriptionItem $item) => [$item->catalog_type.':'.$item->catalog_key => true])
            ->all();

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

        $newItems = $subscription->getConnection()->transaction(function () use ($subscription, $quote): array {
            $created = [];

            foreach ($quote['lines'] as $line) {
                $created[] = SubscriptionItem::query()->create([
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
                    'status' => SubscriptionItemStatus::Pending,
                    'starts_at' => null,
                    'ends_at' => null,
                    'activation_source' => 'subscription',
                    'metadata' => array_merge($line['metadata'], ['upgrade' => true]),
                ]);
            }

            return $created;
        });

        $invoice = $this->invoices->createForSubscription($subscription, $newItems, null, [
            'type' => 'upgrade',
        ]);

        $this->audit->record($subscription->tenant, 'subscription.upgrade_requested', $subscription, [
            'invoice_id' => $invoice->getKey(),
        ]);

        return $invoice;
    }

    public function scheduleDowngrade(
        Subscription $subscription,
        SubscriptionItem $item,
    ): SubscriptionItem {
        if ($item->subscription_id !== $subscription->getKey()) {
            throw new DomainException('Subscription item does not belong to the subscription.');
        }

        if ($subscription->status !== SubscriptionStatus::Active) {
            throw new DomainException('Only an active subscription can schedule a downgrade.');
        }

        if ($item->status !== SubscriptionItemStatus::Active) {
            throw new DomainException('Only an active subscription item can be scheduled for removal.');
        }

        if ($item->catalog_type === 'feature') {
            $feature = Feature::query()->where('key', $item->catalog_key)->first();
            if ($feature?->is_required) {
                throw new DomainException('Required Features cannot be removed independently.');
            }
        }

        $item->update([
            'status' => SubscriptionItemStatus::Scheduled,
            'ends_at' => $subscription->current_period_end,
        ]);

        $this->projector->project($subscription->fresh('items'));
        $this->audit->record($subscription->tenant, 'subscription.item_downgrade_scheduled', $item, [
            'ends_at' => $item->ends_at?->toIso8601String(),
        ]);

        return $item->refresh();
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
                    $at ??= CarbonImmutable::now();

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

                    $this->audit->record($subscription->tenant, 'subscription.renewal_pending_payment', $subscription, [
                        'invoice_id' => $invoice->getKey(),
                    ]);

                    return $invoice;
                }


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

    private function advancePeriod(CarbonImmutable $from, string $billingCycle): CarbonImmutable
    {
        return strtolower($billingCycle) === 'yearly'
            ? $from->addYear()
            : $from->addMonth();
    }
}
