<?php

namespace App\Application\Billing;

use App\Application\Entitlements\EntitlementService;
use App\Domain\Billing\SubscriptionItemStatus;
use App\Domain\Billing\SubscriptionStatus;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Entitlements\EntitlementStatus;
use App\Models\Bundle;
use App\Models\Feature;
use App\Models\Module;
use App\Models\Subscription;
use App\Models\TenantEntitlement;
use DomainException;

final class BillingEntitlementProjector
{
    public function __construct(
        private readonly EntitlementService $entitlements,
    ) {
    }

    public function project(Subscription $subscription): void
    {
        $subscription->loadMissing('items');
        $grants = [];

        foreach ($this->manualRoots($subscription->tenant_id) as $manual) {
            $item = $this->findCatalogItem($manual->catalog_type, $manual->catalog_key);

            if ($item !== null) {
                $grants[] = [
                    'item' => $item,
                    'source' => EntitlementSource::Manual,
                    'status' => $manual->status->value,
                    'quantity' => $manual->quantity,
                    'starts_at' => $manual->starts_at,
                    'ends_at' => $manual->ends_at,
                    'metadata' => $manual->metadata,
                ];
            }
        }

        if ($subscription->status->grantsEntitlements()) {
            foreach ($subscription->items as $item) {
                if (! in_array($item->status, [
                    SubscriptionItemStatus::Active,
                    SubscriptionItemStatus::Scheduled,
                ], true)) {
                    continue;
                }

                $source = $subscription->status === SubscriptionStatus::Trialing
                    ? EntitlementSource::Trial
                    : ($item->catalog_type === 'bundle'
                        ? EntitlementSource::Bundle
                        : EntitlementSource::Subscription);

                $status = $item->status === SubscriptionItemStatus::Scheduled
                    ? EntitlementStatus::ScheduledForRemoval
                    : EntitlementStatus::Active;

                $catalog = $this->findCatalogItem($item->catalog_type, $item->catalog_key);
                if ($catalog === null) {
                    throw new DomainException("Missing catalog item {$item->catalog_type}:{$item->catalog_key}.");
                }

                if ($catalog instanceof Bundle) {
                    foreach ($catalog->modules as $module) {
                        $grants[] = $this->grantPayload(
                            $module,
                            $source,
                            $status,
                            $item->quantity,
                            $item->starts_at ?? $subscription->current_period_start,
                            $item->ends_at ?? $subscription->current_period_end,
                            $item->getKey(),
                        );
                    }

                    foreach ($catalog->features as $feature) {
                        $grants[] = $this->grantPayload(
                            $feature,
                            $source,
                            $status,
                            $item->quantity,
                            $item->starts_at ?? $subscription->current_period_start,
                            $item->ends_at ?? $subscription->current_period_end,
                            $item->getKey(),
                        );
                    }

                    continue;
                }

                $grants[] = $this->grantPayload(
                    $catalog,
                    $source,
                    $status,
                    $item->quantity,
                    $item->starts_at ?? $subscription->current_period_start,
                    $item->ends_at ?? $subscription->current_period_end,
                    $item->getKey(),
                );
            }
        }

        $this->entitlements->rebuildProjection($subscription->tenant, $grants);
    }

    private function grantPayload(
        Module|Feature $item,
        EntitlementSource $source,
        EntitlementStatus $status,
        int $quantity,
        $startsAt,
        $endsAt,
        string $sourceReference,
    ): array {
        return [
            'item' => $item,
            'source' => $source,
            'status' => $status->value,
            'quantity' => $quantity,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'source_reference' => $sourceReference,
        ];
    }

    private function manualRoots(string $tenantId): iterable
    {
        return TenantEntitlement::query()
            ->where('tenant_id', $tenantId)
            ->where('source', EntitlementSource::Manual->value)
            ->whereIn('status', [
                EntitlementStatus::Active->value,
                EntitlementStatus::ScheduledForRemoval->value,
            ])
            ->get();
    }

    private function findCatalogItem(string $type, string $key): Module|Feature|Bundle|null
    {
        return match ($type) {
            'module' => Module::query()->where('key', $key)->first(),
            'feature' => Feature::query()->where('key', $key)->first(),
            'bundle' => Bundle::query()->where('key', $key)->first(),
            default => null,
        };
    }
}
