<?php

use App\Application\Billing\PaymentService;
use App\Application\Billing\SubscriptionService;
use App\Application\Catalog\ModuleMarketplaceService;
use App\Application\Catalog\CatalogManager;
use App\Domain\Billing\SubscriptionItemStatus;
use App\Domain\Tenancy\TenantContext;
use App\Models\CatalogPrice;
use App\Models\Feature;
use App\Models\Module;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function marketplaceFeature(Tenant $tenant, string $key, int $amount = 3000): Feature
{
    $module = Module::factory()->create([
        'key' => $key.'-module',
        'name' => 'Marketplace Test Module',
        'status' => 'active',
        'is_core' => false,
    ]);

    $feature = Feature::factory()->create([
        'module_id' => $module->getKey(),
        'key' => $key,
        'name' => 'Marketplace Test Feature',
        'status' => 'active',
        'is_individually_purchasable' => true,
    ]);

    app(CatalogManager::class)->addPrice($feature, [
        'billing_cycle' => 'monthly',
        'currency' => 'EGP',
        'amount_minor' => $amount,
        'effective_from' => now()->subMinute(),
    ]);

    return $feature;
}

function activeMarketplaceSubscription(Tenant $tenant, Feature $feature): Subscription
{
    $subscription = app(SubscriptionService::class)->create($tenant, [
        ['item' => $feature],
    ], 'monthly', 0);

    $invoice = $subscription->invoices()->latest('issued_at')->firstOrFail();
    $payment = app(PaymentService::class)->createPending($invoice);
    app(PaymentService::class)->markSucceeded($payment);

    return $subscription->fresh('items');
}

test('marketplace overview identifies owned and available catalog items', function (): void {
    $tenant = Tenant::factory()->create([
        'default_currency' => 'EGP',
        'country_code' => 'EG',
    ]);

    $owned = marketplaceFeature($tenant, 'marketplace.owned.'.Str::lower(Str::random(6)));
    $available = marketplaceFeature($tenant, 'marketplace.available.'.Str::lower(Str::random(6)));

    activeMarketplaceSubscription($tenant, $owned);

    app(TenantContext::class)->set($tenant);

    $overview = app(ModuleMarketplaceService::class)->overview($tenant);

    $ownedRow = $overview['features']->firstWhere('catalog_key', $owned->key);
    $availableRow = $overview['features']->firstWhere('catalog_key', $available->key);

    expect($ownedRow['state'])->toBe('active')
        ->and($ownedRow['amount_minor'])->toBe(3000)
        ->and($availableRow['state'])->toBe('available')
        ->and($availableRow['amount_minor'])->toBe(3000);
});

test('marketplace purchase creates a pending upgrade invoice for an active subscription', function (): void {
    $tenant = Tenant::factory()->create([
        'default_currency' => 'EGP',
        'country_code' => 'EG',
    ]);

    $base = marketplaceFeature($tenant, 'marketplace.base.'.Str::lower(Str::random(6)));
    $extra = marketplaceFeature($tenant, 'marketplace.purchase.'.Str::lower(Str::random(6)));

    $subscription = activeMarketplaceSubscription($tenant, $base);
    app(TenantContext::class)->set($tenant);

    $invoice = app(ModuleMarketplaceService::class)->requestPurchase(
        tenant: $tenant,
        catalogType: 'feature',
        catalogKey: $extra->key,
    );

    $pending = $subscription->fresh('items')->items->firstWhere('catalog_key', $extra->key);

    expect($pending)->not->toBeNull()
        ->and($pending->status)->toBe(SubscriptionItemStatus::Pending)
        ->and($invoice->status->value)->toBe('open')
        ->and($invoice->metadata['type'])->toBe('upgrade')
        ->and($invoice->items()->where('subscription_item_id', $pending->getKey())->exists())->toBeTrue();
});

test('marketplace purchase requires an active or grace subscription', function (): void {
    $tenant = Tenant::factory()->create();
    $feature = marketplaceFeature($tenant, 'marketplace.no-subscription.'.Str::lower(Str::random(6)));

    app(TenantContext::class)->set($tenant);

    expect(fn () => app(ModuleMarketplaceService::class)->requestPurchase(
        tenant: $tenant,
        catalogType: 'feature',
        catalogKey: $feature->key,
    ))->toThrow(DomainException::class, 'An active or grace subscription is required to purchase a Marketplace item.');
});

test('marketplace purchase rejects inactive catalog items', function (): void {
    $tenant = Tenant::factory()->create();
    $feature = marketplaceFeature($tenant, 'marketplace.inactive.'.Str::lower(Str::random(6)));
    $feature->update(['status' => 'inactive']);

    app(TenantContext::class)->set($tenant);

    expect(fn () => app(ModuleMarketplaceService::class)->requestPurchase(
        tenant: $tenant,
        catalogType: 'feature',
        catalogKey: $feature->key,
    ))->toThrow(DomainException::class, 'Only active Marketplace items can be purchased.');
});

test('marketplace tenant context cannot be used for another tenant', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $feature = marketplaceFeature($tenantB, 'marketplace.isolation.'.Str::lower(Str::random(6)));

    app(TenantContext::class)->set($tenantA);

    expect(fn () => app(ModuleMarketplaceService::class)->overview($tenantB))
        ->toThrow(DomainException::class, 'Marketplace tenant context does not match the requested tenant.');
});
