<?php

namespace App\Application\Billing;

use App\Application\Billing\Pricing\PricingBreakdown;
use App\Application\Billing\Pricing\PricingEngine;
use App\Domain\Billing\BillingCycle;
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
use Illuminate\Database\DatabaseManager;

final class SubscriptionService
{
    private const TRIAL_DAYS = 14;

    public function __construct(
        private readonly PricingEngine $pricing,
        private readonly InvoiceService $invoices,
        private readonly EntitlementProjector $projector,
        private readonly BillingAuditService $audit,
        private readonly DatabaseManager $database,
    ) {
    }

    /**
     * @param array<int, array{item: Module|Feature|Bundle, quantity?: int}> $selections
     */
    public function start(
        Tenant $tenant,
        array $selections,
        BillingCycle $cycle,
        ?string $currency = null,
        int $taxBps = 0,
        bool $trial = true,
    ): Subscription {
        return $this->database->connection('central')->transaction(function () use (
            $tenant, $selections, $cycle, $currency, $taxBps, $trial,
        ): Subscription {
            $tenant = Tenant::query()->lockForUpdate()->findOrFail($tenant->getKey());

            $existing = Subscription::query()
                ->where('tenant_id', $tenant->getKey())
                ->whereIn('status', [
                    SubscriptionStatus::Trialing->value,
                    SubscriptionStatus::PendingPayment->value,
                    SubscriptionStatus::Active->value,
                ])
                ->exists();

            if ($existing) {
                throw new DomainException('Tenant already has a current subscription.');
            }

            $currency = strtoupper($currency ?? $tenant->default_currency);

            $breakdown = $this->pricing->calculate(
                $tenant,
                $selections,
                $cycle,
                $currency,
                $tenant->country_code,
                $taxBps,
            );

            $now = CarbonImmutable::now();
            $trialEnds = $trial ? $now->addDays(self::TRIAL_DAYS) : null;
            $periodStart = $trialEnds ?? $now;
            $periodEnd = $cycle === BillingCycle::Yearly
                ? $periodStart->addYearNoOverflow()
                : $periodStart->addMonthNoOverflow();

            $subscription = Subscription::query()->create([
                'tenant_id' => $tenant->getKey(),
                'status' => $trial ? SubscriptionStatus::Trialing : SubscriptionStatus::PendingPayment,
                'billing_cycle' => $cycle,
                'currency' => $breakdown->currency,
                'starts_at' => $now,
                'trial_ends_at' => $trialEnds,
                'current_period_start' => $periodStart,
                'current_period_end' => $periodEnd,
                'next_billed_at' => $trialEnds ?? $now,
                'cancel_at_period_end' => false,
                'cancelled_at' => null,
                'metadata' => ['tax_bps' => $taxBps],
            ]);

            $this->createItems($subscription, $breakdown, $trial);

            if (! $trial) {
                $this->invoices->createForSubscription($subscription, ['pending']);
            }

            $this->projector->sync($subscription);

            $this->audit->record(
                $tenant,
                'subscription.created',
                $subscription,
                null,
                $subscription->status->value,
            );

            return $subscription->refresh();
        });
    }

    /**
     * @param array<int, array{item: Module|Feature|Bundle, quantity?: int}> $selections
     */
    public function addItems(
        Subscription $subscription,
        array $selections,
        int $taxBps = 0,
    ): ?PlatformInvoice {
        return $this->database->connection('central')->transaction(function () use ($subscription, $selections, $taxBps): ?PlatformInvoice {
            $subscription = Subscription::query()
                ->with('tenant')
                ->lockForUpdate()
                ->findOrFail($subscription->getKey());

            if (! in_array($subscription->status, [SubscriptionStatus::Trialing, SubscriptionStatus::Active], true)) {
                throw new DomainException('Only current subscriptions can be upgraded.');
            }

            $breakdown = $this->pricing->calculate(
                $subscription->tenant,
                $selections,
                $subscription->billing_cycle,
                $subscription->currency,
                $subscription->tenant->country_code,
                $taxBps,
            );

            $existingKeys = $subscription->items()
                ->whereIn('status', [
                    SubscriptionItemStatus::Trialing->value,
                    SubscriptionItemStatus::Active->value,
                    SubscriptionItemStatus::Pending->value,
                    SubscriptionItemStatus::ScheduledForRemoval->value,
                ])
                ->get()
                ->map(fn (SubscriptionItem $item): string => $item->catalog_type.':'.$item->catalog_key)
                ->all();

            foreach ($breakdown->lines as $line) {
                if (in_array($line->catalogType.':'.$line->catalogKey, $existingKeys, true)) {
                    throw new DomainException("Subscription already contains {$line->catalogKey}.");
                }
            }

            $trial = $subscription->status === SubscriptionStatus::Trialing;

            $this->createItems($subscription, $breakdown, $trial);

            $this->audit->record(
                $subscription->tenant,
                'subscription.items_added',
                $subscription,
                null,
                $subscription->status->value,
                null,
                ['items_count' => count($breakdown->lines), 'trial' => $trial],
            );

            if ($trial) {
                $this->projector->sync($subscription->refresh());

                return null;
            }

            $invoice = $this->invoices->createForSubscription($subscription, ['pending']);
            $this->projector->sync($subscription->refresh());

            return $invoice;
        });
    }

    public function scheduleRemoval(SubscriptionItem $item): SubscriptionItem
    {
        return $this->database->connection('central')->transaction(function () use ($item): SubscriptionItem {
            $item = SubscriptionItem::query()
                ->with('subscription.tenant')
                ->lockForUpdate()
                ->findOrFail($item->getKey());

            if ($item->status !== SubscriptionItemStatus::Active) {
                throw new DomainException('Only active subscription items can be downgraded.');
            }

            $item->update([
                'status' => SubscriptionItemStatus::ScheduledForRemoval,
                'ends_at' => $item->subscription->current_period_end,
            ]);

            $this->audit->record(
                $item->subscription->tenant,
                'subscription.item_removal_scheduled',
                $item,
                SubscriptionItemStatus::Active->value,
                SubscriptionItemStatus::ScheduledForRemoval->value,
            );

            $this->projector->sync($item->subscription->refresh());

            return $item->refresh();
        });
    }

    public function cancelAtPeriodEnd(Subscription $subscription): Subscription
    {
        return $this->database->connection('central')->transaction(function () use ($subscription): Subscription {
            $subscription = Subscription::query()->with('tenant')->lockForUpdate()->findOrFail($subscription->getKey());

            if (! in_array($subscription->status, [SubscriptionStatus::Trialing, SubscriptionStatus::Active], true)) {
                throw new DomainException('Only current subscriptions can be cancelled.');
            }

            $subscription->update(['cancel_at_period_end' => true]);

            $this->audit->record(
                $subscription->tenant,
                'subscription.cancel_scheduled',
                $subscription,
                $subscription->status->value,
                $subscription->status->value,
            );

            return $subscription->refresh();
        });
    }

    public function activateAfterPayment(Subscription $subscription): Subscription
    {
        $subscription = Subscription::query()->with('tenant')->findOrFail($subscription->getKey());

        if (! in_array($subscription->status, [
            SubscriptionStatus::PendingPayment,
            SubscriptionStatus::Trialing,
            SubscriptionStatus::PastDue,
            SubscriptionStatus::Active,
        ], true)) {
            return $subscription;
        }

        $from = $subscription->status->value;

        $subscription->update([
            'status' => SubscriptionStatus::Active,
            'next_billed_at' => $subscription->current_period_end,
        ]);

        $subscription->items()
            ->whereIn('status', [
                SubscriptionItemStatus::Pending->value,
                SubscriptionItemStatus::Trialing->value,
            ])
            ->update([
                'status' => SubscriptionItemStatus::Active,
                'ends_at' => $subscription->current_period_end,
            ]);

        $subscription = $subscription->refresh();

        if ($from !== SubscriptionStatus::Active->value) {
            $this->audit->record(
                $subscription->tenant,
                'subscription.activated',
                $subscription,
                $from,
                SubscriptionStatus::Active->value,
            );
        }

        $this->projector->sync($subscription);

        return $subscription;
    }

    public function expireTrial(Subscription $subscription): PlatformInvoice
    {
        return $this->database->connection('central')->transaction(function () use ($subscription): PlatformInvoice {
            $subscription = Subscription::query()->with('tenant')->findOrFail($subscription->getKey());

            if ($subscription->status !== SubscriptionStatus::Trialing) {
                throw new DomainException('Only trialing subscriptions can expire.');
            }

            $invoice = $this->invoices->createForSubscription($subscription, ['trialing']);

            $subscription->items()
                ->where('status', SubscriptionItemStatus::Trialing->value)
                ->update(['status' => SubscriptionItemStatus::Pending]);

            $subscription->update([
                'status' => SubscriptionStatus::PastDue,
                'next_billed_at' => null,
            ]);

            $this->projector->sync($subscription->refresh());

            return $invoice;
        });
    }

    public function renewalInvoice(Subscription $subscription): PlatformInvoice
    {
        $subscription = Subscription::query()->with('tenant')->findOrFail($subscription->getKey());

        if ($subscription->status !== SubscriptionStatus::Active) {
            throw new DomainException('Only active subscriptions can create a renewal invoice.');
        }

        return $this->invoices->createForSubscription(
            $subscription,
            ['active'],
            $subscription->current_period_end->toIso8601String(),
        );
    }

    private function createItems(Subscription $subscription, PricingBreakdown $breakdown, bool $trial): void
    {
        foreach ($breakdown->lines as $line) {
            $subscription->items()->create([
                'item_type' => $line->catalogType,
                'catalog_type' => $line->catalogType,
                'catalog_key' => $line->catalogKey,
                'catalog_price_id' => $line->catalogPriceId,
                'quantity' => $line->quantity,
                'unit_amount_minor' => $line->unitAmountMinor,
                'currency' => $breakdown->currency,
                'discount_amount_minor' => $line->discountMinor,
                'tax_amount_minor' => $line->taxMinor,
                'total_amount_minor' => $line->lineTotalMinor + $line->taxMinor,
                'status' => $trial ? SubscriptionItemStatus::Trialing : SubscriptionItemStatus::Pending,
                'activation_source' => $trial ? 'trial' : 'subscription',
                'starts_at' => $subscription->starts_at,
                'ends_at' => $subscription->current_period_end,
                'metadata' => null,
            ]);
        }
    }
}