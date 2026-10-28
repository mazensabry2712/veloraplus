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



test('prospective customers can browse catalog prices before subscribing', function (): void {
    $tenant = Tenant::factory()->create([
        'default_currency' => 'EGP',
        'country_code' => 'EG',
    ]);
    $feature = marketplaceFeature($tenant, 'marketplace.discovery.'.Str::lower(Str::random(6)), 2750);

    app(TenantContext::class)->set($tenant);

    $overview = app(ModuleMarketplaceService::class)->overview($tenant);
    $row = $overview['features']->firstWhere('catalog_key', $feature->key);

    expect($overview['subscription'])->toBeNull()
        ->and($row['state'])->toBe('available')
        ->and($row['amount_minor'])->toBe(2750)
        ->and($row['billing_cycle'])->toBe('monthly');
});

test('marketplace quote preview is read-only and returns dependency-aware price lines', function (): void {
    $tenant = Tenant::factory()->create([
        'default_currency' => 'EGP',
        'country_code' => 'EG',
    ]);

    $base = marketplaceFeature($tenant, 'marketplace.preview.base.'.Str::lower(Str::random(6)));
    $extra = marketplaceFeature($tenant, 'marketplace.preview.extra.'.Str::lower(Str::random(6)), 4500);
    $dependency = marketplaceFeature($tenant, 'marketplace.preview.dependency.'.Str::lower(Str::random(6)), 1500);
    $included = marketplaceFeature($tenant, 'marketplace.preview.included.'.Str::lower(Str::random(6)), 1000);
    $free = marketplaceFeature($tenant, 'marketplace.preview.free.'.Str::lower(Str::random(6)), 500);

    $subscription = activeMarketplaceSubscription($tenant, $base);
    $subscription->update(['metadata' => ['tax_bps' => 1000]]);
    app(CatalogManager::class)->addDependency($extra, $dependency, 'billable');
    app(CatalogManager::class)->addDependency($extra, $included, 'included');
    app(CatalogManager::class)->addDependency($extra, $free, 'free');
    app(TenantContext::class)->set($tenant);

    $itemCountBefore = $subscription->fresh('items')->items->count();
    $invoiceCountBefore = $subscription->invoices()->count();

    $preview = app(ModuleMarketplaceService::class)->previewPurchase(
        tenant: $tenant,
        catalogType: 'feature',
        catalogKey: $extra->key,
        quantity: 2,
    );

    expect($preview['selection']['catalog_key'])->toBe($extra->key)
        ->and($preview['selection']['quantity'])->toBe(2)
        ->and($preview['subtotal_minor'])->toBe(12000)
        ->and($preview['tax_minor'])->toBe(1200)
        ->and($preview['total_minor'])->toBe(13200)
        ->and($preview['lines'])->toHaveCount(2)
        ->and(collect($preview['lines'])->firstWhere('catalog_key', $dependency->key)['metadata']['commercial_origin'])
            ->toBe('required_dependency')
        ->and(collect($preview['dependency_explanations'])->firstWhere('catalog_key', $dependency->key)['treatment'])
            ->toBe('billed_separately')
        ->and(collect($preview['dependency_explanations'])->firstWhere('catalog_key', $included->key)['treatment'])
            ->toBe('included')
        ->and(collect($preview['dependency_explanations'])->firstWhere('catalog_key', $free->key)['treatment'])
            ->toBe('free')
        ->and($preview['creates_invoice'])->toBeFalse()
        ->and($preview['requires_payment'])->toBeTrue()
        ->and($subscription->fresh('items')->items)->toHaveCount($itemCountBefore)
        ->and($subscription->invoices()->count())->toBe($invoiceCountBefore);
});


test('marketplace quote confirmation token changes when the quoted price changes', function (): void {
    $tenant = Tenant::factory()->create([
        'default_currency' => 'EGP',
        'country_code' => 'EG',
    ]);

    $base = marketplaceFeature($tenant, 'marketplace.token.base.'.Str::lower(Str::random(6)));
    $extra = marketplaceFeature($tenant, 'marketplace.token.extra.'.Str::lower(Str::random(6)), 3500);
    activeMarketplaceSubscription($tenant, $base);
    app(TenantContext::class)->set($tenant);

    $marketplace = app(ModuleMarketplaceService::class);
    $first = $marketplace->previewPurchase($tenant, 'feature', $extra->key, 1);
    $same = $marketplace->previewPurchase($tenant, 'feature', $extra->key, 1);

    CatalogPrice::query()
        ->where('priceable_type', $extra->getMorphClass())
        ->where('priceable_id', $extra->getKey())
        ->where('billing_cycle', 'monthly')
        ->where('currency', 'EGP')
        ->update(['amount_minor' => 4200]);

    $second = $marketplace->previewPurchase($tenant, 'feature', $extra->key, 1);

    expect($first['confirmation_token'])->toMatch('/^[a-f0-9]{64}$/')
        ->and($same['confirmation_token'])->toBe($first['confirmation_token'])
        ->and($second['confirmation_token'])->toMatch('/^[a-f0-9]{64}$/')
        ->and($second['confirmation_token'])->not->toBe($first['confirmation_token'])
        ->and($second['total_minor'])->toBeGreaterThan($first['total_minor']);
});

test('marketplace purchase rejects a stale quote inside the locked upgrade transaction', function (): void {
    $tenant = Tenant::factory()->create([
        'default_currency' => 'EGP',
        'country_code' => 'EG',
    ]);

    $base = marketplaceFeature($tenant, 'marketplace.stale.base.'.Str::lower(Str::random(6)));
    $extra = marketplaceFeature($tenant, 'marketplace.stale.extra.'.Str::lower(Str::random(6)), 3500);
    $subscription = activeMarketplaceSubscription($tenant, $base);
    app(TenantContext::class)->set($tenant);

    $marketplace = app(ModuleMarketplaceService::class);
    $staleQuote = $marketplace->previewPurchase($tenant, 'feature', $extra->key, 1);
    $invoiceCount = $subscription->invoices()->count();
    $itemCount = $subscription->fresh('items')->items->count();

    CatalogPrice::query()
        ->where('priceable_type', $extra->getMorphClass())
        ->where('priceable_id', $extra->getKey())
        ->where('billing_cycle', 'monthly')
        ->where('currency', 'EGP')
        ->update(['amount_minor' => 4200]);

    expect(fn () => $marketplace->requestPurchase(
        tenant: $tenant,
        catalogType: 'feature',
        catalogKey: $extra->key,
        quantity: 1,
        quoteToken: $staleQuote['confirmation_token'],
    ))->toThrow(DomainException::class, 'price or required items changed');

    expect($subscription->invoices()->count())->toBe($invoiceCount)
        ->and($subscription->fresh('items')->items)->toHaveCount($itemCount);
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

    $marketplace = app(ModuleMarketplaceService::class);
    $quote = $marketplace->previewPurchase($tenant, 'feature', $extra->key, 1);

    $invoice = $marketplace->requestPurchase(
        tenant: $tenant,
        catalogType: 'feature',
        catalogKey: $extra->key,
        quantity: 1,
        quoteToken: $quote['confirmation_token'],
    );

    $pending = $subscription->fresh('items')->items->firstWhere('catalog_key', $extra->key);

    expect($pending)->not->toBeNull()
        ->and($pending->status)->toBe(SubscriptionItemStatus::Pending)
        ->and($invoice->status->value)->toBe('open')
        ->and($invoice->metadata['type'])->toBe('upgrade')
        ->and($invoice->items()->where('subscription_item_id', $pending->getKey())->exists())->toBeTrue();
});

test('marketplace does not allow repurchasing a feature granted by a bundle', function (): void {
    $tenant = Tenant::factory()->create([
        'default_currency' => 'EGP',
        'country_code' => 'EG',
    ]);

    $module = Module::factory()->create([
        'key' => 'marketplace.bundle.module.'.Str::lower(Str::random(6)),
        'name' => 'Marketplace Bundle Module',
        'status' => 'active',
    ]);

    $feature = Feature::factory()->create([
        'module_id' => $module->getKey(),
        'key' => 'marketplace.bundle.feature.'.Str::lower(Str::random(6)),
        'name' => 'Marketplace Bundle Feature',
        'status' => 'active',
        'is_individually_purchasable' => true,
    ]);

    $bundle = \App\Models\Bundle::factory()->create([
        'key' => 'marketplace.bundle.'.Str::lower(Str::random(6)),
        'name' => 'Marketplace Bundle',
        'status' => 'active',
    ]);

    app(CatalogManager::class)->addModuleToBundle($bundle, $module);
    app(CatalogManager::class)->addFeatureToBundle($bundle, $feature);
    app(CatalogManager::class)->addPrice($module, [
        'billing_cycle' => 'monthly',
        'currency' => 'EGP',
        'amount_minor' => 4000,
        'effective_from' => now()->subMinute(),
    ]);
    app(CatalogManager::class)->addPrice($feature, [
        'billing_cycle' => 'monthly',
        'currency' => 'EGP',
        'amount_minor' => 3000,
        'effective_from' => now()->subMinute(),
    ]);
    app(CatalogManager::class)->addPrice($bundle, [
        'billing_cycle' => 'monthly',
        'currency' => 'EGP',
        'amount_minor' => 6000,
        'effective_from' => now()->subMinute(),
    ]);

    $subscription = app(SubscriptionService::class)->create($tenant, [
        ['item' => $bundle],
    ], 'monthly', 0);

    $invoice = $subscription->invoices()->latest('issued_at')->firstOrFail();
    $payment = app(PaymentService::class)->createPending($invoice);
    app(PaymentService::class)->markSucceeded($payment);

    app(TenantContext::class)->set($tenant);

    expect(fn () => app(ModuleMarketplaceService::class)->requestPurchase(
        tenant: $tenant,
        catalogType: 'feature',
        catalogKey: $feature->key,
    ))->toThrow(DomainException::class, "Marketplace item {$feature->key} is already active.");
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
