<?php

namespace App\Application\Billing;

use App\Application\Entitlements\EntitlementService;
use App\Domain\Billing\SubscriptionItemStatus;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Entitlements\EntitlementStatus;
use App\Models\Bundle;
use App\Models\Feature;
use App\Models\Module;
use App\Models\Subscription;
use App\Models\SubscriptionItem;
use App\Models\Tenant;
use App\Models\TenantEntitlement;

final class EntitlementProjector
{
    public function __construct(
        private readonly EntitlementService $entitlements,
    ) {
    }

    public function sync(Subscription $subscription): void
    {
        $subscription->loadMissing(['tenant', 'items']);

        $grants = TenantEntitlement::query()
            ->where('tenant_id', $subscription->tenant_id)
            ->where('source', EntitlementSource::Manual->value)
            ->get()
            ->map(fn (TenantEntitlement $item): array => [
                'item' => $this->catalogItem($item->catalog_type, $item->catalog_key),
                'source' => EntitlementSource::Manual,
                'status' => $item->status->value,
                'quantity' => $item->quantity,
                'starts_at' => $item->starts_at,
                'ends_at' => $item->ends_at,
                'metadata' => $item->metadata,
            ])
            ->all();

        if ($subscription->status->value === 'trialing' || $subscription->status->value === 'active') {
            foreach ($subscription->items as $item) {
                if (! in_array($item->status, [
                    SubscriptionItemStatus::Trialing,
                    SubscriptionItemStatus::Active,
                    SubscriptionItemStatus::ScheduledForRemoval,
                ], true)) {
                    continue;
                }

                foreach ($this->expand($item) as $catalogItem) {
                    $grants[] = [
                        'item' => $catalogItem,
                        'source' => $item->activation_source === EntitlementSource::Trial->value
                            ? EntitlementSource::Trial
                            : EntitlementSource::Subscription,
                        'status' => $item->status === SubscriptionItemStatus::ScheduledForRemoval
                            ? EntitlementStatus::ScheduledForRemoval->value
                            : EntitlementStatus::Active->value,
                        'quantity' => $item->quantity,
                        'starts_at' => $item->starts_at,
                        'ends_at' => $item->ends_at,
                        'metadata' => ['subscription_item_id' => $item->getKey()],
                    ];
                }
            }
        }

        $this->entitlements->rebuildProjection($subscription->tenant, $grants);
    }

    /**
     * @return array<int, Module|Feature>
     */
    private function expand(SubscriptionItem $item): array
    {
        if ($item->catalog_type === 'module') {
            return [Module::query()->where('key', $item->catalog_key)->firstOrFail()];
        }

        if ($item->catalog_type === 'feature') {
            return [Feature::query()->where('key', $item->catalog_key)->firstOrFail()];
        }

        if ($item->catalog_type === 'bundle') {
            $bundle = Bundle::query()
                ->with(['modules', 'features'])
                ->where('key', $item->catalog_key)
                ->firstOrFail();

            return $bundle->modules
                ->concat($bundle->features)
                ->unique(fn ($catalogItem): string => $catalogItem->getMorphClass().':'.$catalogItem->getKey())
                ->values()
                ->all();
        }

        return [];
    }

    private function catalogItem(string $type, string $key): Module|Feature
    {
        return $type === 'module'
            ? Module::query()->where('key', $key)->firstOrFail()
            : Feature::query()->where('key', $key)->firstOrFail();
    }
}