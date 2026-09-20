<?php

namespace App\Application\Catalog;

use App\Application\Billing\PlatformBillingDashboardService;
use App\Application\Entitlements\EntitlementService;
use App\Domain\Billing\SubscriptionItemStatus;
use App\Domain\Entitlements\EntitlementStatus;
use App\Domain\Tenancy\TenantContext;
use App\Models\Bundle;
use App\Models\Feature;
use App\Models\Module;
use App\Models\Subscription;
use App\Models\Tenant;
use DomainException;

final class ModuleMarketplaceService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly CatalogPricingResolver $prices,
        private readonly EntitlementService $entitlements,
        private readonly PlatformBillingDashboardService $billing,
        private readonly CatalogDependencyResolver $dependencies,
    ) {
    }

    public function overview(Tenant $tenant): array
    {
        $this->assertCurrentTenant($tenant);

        $subscription = Subscription::query()
            ->where('tenant_id', $tenant->getKey())
            ->whereIn('status', [
                'pending_payment',
                'trialing',
                'active',
                'past_due',
                'grace',
                'suspended',
            ])
            ->orderByDesc('starts_at')
            ->first();

        return [
            'subscription' => $subscription,
            'modules' => Module::query()
                ->where('status', 'active')
                ->where('is_core', false)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(fn (Module $item) => $this->present($tenant, $subscription, $item))
                ->values(),
            'features' => Feature::query()
                ->with('module')
                ->where('status', 'active')
                ->where('is_individually_purchasable', true)
                ->whereHas('module', fn ($query) => $query
                    ->where('status', 'active')
                    ->where('is_core', false))
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(fn (Feature $item) => $this->present($tenant, $subscription, $item))
                ->values(),
            'bundles' => Bundle::query()
                ->where('status', 'active')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(fn (Bundle $item) => $this->present($tenant, $subscription, $item))
                ->values(),
        ];
    }

    public function requestPurchase(
        Tenant $tenant,
        string $catalogType,
        string $catalogKey,
        int $quantity = 1,
    ) {
        $this->assertCurrentTenant($tenant);

        if ($quantity < 1 || $quantity > 1000) {
            throw new DomainException('Marketplace quantity must be between 1 and 1000.');
        }

        $item = $this->findCatalogItem($catalogType, $catalogKey);

        if ($item === null) {
            throw new DomainException('The requested marketplace item was not found.');
        }

        $this->assertMarketplacePurchasable($item);

        $subscription = Subscription::query()
            ->where('tenant_id', $tenant->getKey())
            ->whereIn('status', ['active', 'grace'])
            ->latest('starts_at')
            ->first();

        if ($subscription === null) {
            throw new DomainException('An active or grace subscription is required to purchase a Marketplace item.');
        }

        $ownedState = $this->ownedState($tenant, $subscription, $item);

        if ($ownedState !== null) {
            throw new DomainException("Marketplace item {$item->key} is already {$ownedState}.");
        }

        return $this->billing->requestUpgrade($subscription, [[
            'catalog_type' => $item->getMorphClass(),
            'catalog_key' => $item->key,
            'quantity' => $quantity,
        ]]);
    }

    private function present(Tenant $tenant, ?Subscription $subscription, Module|Feature|Bundle $item): array
    {
        $price = $subscription !== null
            ? $this->prices->current(
                $item,
                $subscription->billing_cycle,
                $tenant->default_currency,
                $tenant->country_code,
            )
            : null;

        $state = $this->state($tenant, $subscription, $item, $price !== null);

        return [
            'catalog_type' => $item->getMorphClass(),
            'catalog_key' => $item->key,
            'name' => $item->name,
            'description' => $item->description,
            'status' => $item->status,
            'state' => $state,
            'currency' => $price?->currency,
            'amount_minor' => $price?->amount_minor,
            'billing_cycle' => $subscription?->billing_cycle,
            'metadata' => $item->metadata,
        ];
    }

    private function ownedState(
        Tenant $tenant,
        Subscription $subscription,
        Module|Feature|Bundle $item,
    ): ?string {
        $subscriptionItem = $subscription->items()
            ->where('catalog_type', $item->getMorphClass())
            ->where('catalog_key', $item->key)
            ->latest()
            ->first();

        if ($subscriptionItem !== null) {
            return match ($subscriptionItem->status) {
                SubscriptionItemStatus::Active => 'active',
                SubscriptionItemStatus::Scheduled => 'scheduled_for_removal',
                SubscriptionItemStatus::Pending => 'pending',
                default => null,
            };
        }

        if ($item instanceof Bundle) {
            return null;
        }

        return match ($this->entitlements->status($tenant, $item->key)) {
            EntitlementStatus::Active => 'active',
            EntitlementStatus::ScheduledForRemoval => 'scheduled_for_removal',
            default => null,
        };
    }

    private function state(
        Tenant $tenant,
        ?Subscription $subscription,
        Module|Feature|Bundle $item,
        bool $hasPrice,
    ): string {
        if (! $hasPrice) {
            return 'unavailable';
        }

        if ($subscription === null) {
            return 'available';
        }

        if ($item instanceof Bundle) {
            $subscriptionItem = $subscription->items()
                ->where('catalog_type', $item->getMorphClass())
                ->where('catalog_key', $item->key)
                ->latest()
                ->first();

            return match ($subscriptionItem?->status) {
                SubscriptionItemStatus::Active => 'active',
                SubscriptionItemStatus::Scheduled => 'scheduled_for_removal',
                SubscriptionItemStatus::Pending => 'pending',
                default => 'available',
            };
        }

        return match ($this->entitlements->status($tenant, $item->key)) {
            EntitlementStatus::Active,
            EntitlementStatus::ScheduledForRemoval => $this->entitlements->status($tenant, $item->key) === EntitlementStatus::ScheduledForRemoval
                ? 'scheduled_for_removal'
                : 'active',
            default => 'available',
        };
    }

    private function assertMarketplacePurchasable(Module|Feature|Bundle $item): void
    {
        if ($item->status !== 'active') {
            throw new DomainException('Only active Marketplace items can be purchased.');
        }

        if ($item instanceof Module && $item->is_core) {
            throw new DomainException('Core modules are not customer-purchasable.');
        }

        if ($item instanceof Feature) {
            if (! $item->is_individually_purchasable) {
                throw new DomainException('This Feature is not individually purchasable.');
            }

            if ($item->module()->first()?->status !== 'active') {
                throw new DomainException('The parent Module is not available for purchase.');
            }
        }

        if ($item instanceof Bundle) {
            $item->loadMissing(['modules', 'features']);

            if ($item->modules->contains(fn (Module $module) => $module->status !== 'active' || $module->is_core)
                || $item->features->contains(fn (Feature $feature) => $feature->status !== 'active')) {
                throw new DomainException('The Bundle contains unavailable catalog items.');
            }
        }

        if (! $this->dependenciesAvailable($item)) {
            throw new DomainException('The requested Marketplace item has unavailable dependencies.');
        }
    }

    private function dependenciesAvailable(Module|Feature|Bundle $item): bool
    {
        $roots = [];

        if ($item instanceof Bundle) {
            $item->loadMissing(['modules', 'features']);
            $roots = [...$item->modules, ...$item->features];
        } else {
            $roots = [$item];
        }

        foreach ($this->dependencies->resolve($roots) as $dependency) {
            if ($dependency->is($item)) {
                continue;
            }

            if ($dependency->status !== 'active') {
                return false;
            }

            if ($dependency instanceof Feature) {
                $dependency->loadMissing('module');

                if ($dependency->module === null || $dependency->module->status !== 'active') {
                    return false;
                }
            }
        }

        return true;
    }

    private function findCatalogItem(string $type, string $key): Module|Feature|Bundle|null
    {
        return match (strtolower(trim($type))) {
            'module' => Module::query()->where('key', strtolower(trim($key)))->first(),
            'feature' => Feature::query()->where('key', strtolower(trim($key)))->first(),
            'bundle' => Bundle::query()->where('key', strtolower(trim($key)))->first(),
            default => null,
        };
    }

    private function assertCurrentTenant(Tenant $tenant): void
    {
        $current = $this->tenantContext->current();

        if ($current === null || $current->getKey() !== $tenant->getKey()) {
            throw new DomainException('Marketplace tenant context does not match the requested tenant.');
        }
    }
}
