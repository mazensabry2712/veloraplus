<?php

use App\Application\Catalog\CatalogManager;
use App\Application\Entitlements\EntitlementService;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Entitlements\EntitlementStatus;
use App\Domain\Tenancy\TenantContext;
use App\Models\Feature;
use App\Models\Module;
use App\Models\Tenant;
use App\Models\TenantEntitlement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

function makeActiveModule(string $key): Module
{
    return Module::factory()->create([
        'key' => $key,
        'name' => $key,
        'status' => 'active',
    ]);
}

function makeActiveFeature(Module $module, string $key): Feature
{
    return Feature::factory()->create([
        'module_id' => $module->getKey(),
        'key' => $key,
        'name' => $key,
        'status' => 'active',
    ]);
}

test('a tenant can receive and query an active feature entitlement', function () {
    $tenant = Tenant::factory()->create();
    $module = makeActiveModule('booking');
    $feature = makeActiveFeature($module, 'booking.queue');

    app(EntitlementService::class)->grant($tenant, $feature);

    expect(app(EntitlementService::class)->hasFeature($tenant, 'booking.queue'))->toBeTrue()
        ->and(app(EntitlementService::class)->status($tenant, 'booking.queue'))
        ->toBe(EntitlementStatus::Active);
});

test('module entitlement makes its active features available', function () {
    $tenant = Tenant::factory()->create();
    $module = makeActiveModule('booking');
    makeActiveFeature($module, 'booking.services');
    makeActiveFeature($module, 'booking.queue');

    app(EntitlementService::class)->grant($tenant, $module);

    expect(app(EntitlementService::class)->hasModule($tenant, 'booking'))->toBeTrue()
        ->and(app(EntitlementService::class)->hasFeature($tenant, 'booking.services'))->toBeTrue()
        ->and(app(EntitlementService::class)->hasFeature($tenant, 'booking.queue'))->toBeTrue();
});

test('inactive or expired entitlements deny access while scheduled removal works until its end time', function () {
    $tenant = Tenant::factory()->create();
    $module = makeActiveModule('booking');
    $feature = makeActiveFeature($module, 'booking.queue');
    $service = app(EntitlementService::class);

    $service->grant($tenant, $feature, [
        'ends_at' => now()->addDay(),
    ]);

    expect($service->hasFeature($tenant, 'booking.queue'))->toBeTrue();

    $service->scheduleRemoval($tenant, $feature, now()->addDay());

    expect($service->hasFeature($tenant, 'booking.queue'))->toBeTrue()
        ->and($service->status($tenant, 'booking.queue'))->toBe(EntitlementStatus::ScheduledForRemoval)
        ->and($service->hasFeature($tenant, 'booking.queue', now()->addDays(2)))->toBeFalse()
        ->and($service->status($tenant, 'booking.queue', now()->addDays(2)))->toBe(EntitlementStatus::Inactive);
});

test('tenant entitlements are isolated between tenants', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $module = makeActiveModule('booking');
    $feature = makeActiveFeature($module, 'booking.queue');

    app(EntitlementService::class)->grant($tenantA, $feature);

    expect(app(EntitlementService::class)->hasFeature($tenantA, 'booking.queue'))->toBeTrue()
        ->and(app(EntitlementService::class)->hasFeature($tenantB, 'booking.queue'))->toBeFalse();
});

test('feature dependencies are automatically projected and required for access', function () {
    $tenant = Tenant::factory()->create();
    $manager = app(CatalogManager::class);
    $service = app(EntitlementService::class);

    $module = makeActiveModule('crm');
    $pipeline = makeActiveFeature($module, 'crm.pipeline');
    $deals = makeActiveFeature($module, 'crm.deals');

    $manager->addDependency($deals, $pipeline);
    $service->grant($tenant, $deals);

    expect($service->hasFeature($tenant, 'crm.deals'))->toBeTrue()
        ->and($service->hasFeature($tenant, 'crm.pipeline'))->toBeTrue()
        ->and(TenantEntitlement::query()->where('tenant_id', $tenant->getKey())->count())->toBe(2);
});

test('direct subscription source takes precedence over dependency source', function () {
    $tenant = Tenant::factory()->create();
    $manager = app(CatalogManager::class);
    $service = app(EntitlementService::class);

    $module = makeActiveModule('crm');
    $pipeline = makeActiveFeature($module, 'crm.pipeline');
    $deals = makeActiveFeature($module, 'crm.deals');
    $manager->addDependency($deals, $pipeline);

    $service->grant($tenant, $deals, [
        'source' => EntitlementSource::Dependency,
    ]);

    $service->grant($tenant, $pipeline, [
        'source' => EntitlementSource::Subscription,
        'quantity' => 5,
    ]);

    $entitlement = TenantEntitlement::query()
        ->where('tenant_id', $tenant->getKey())
        ->where('catalog_type', 'feature')
        ->where('catalog_key', 'crm.pipeline')
        ->firstOrFail();

    expect($entitlement->source)->toBe(EntitlementSource::Subscription)
        ->and($entitlement->quantity)->toBe(5);
});

test('revoke removes effective access without deleting the entitlement row', function () {
    $tenant = Tenant::factory()->create();
    $module = makeActiveModule('booking');
    $feature = makeActiveFeature($module, 'booking.queue');
    $service = app(EntitlementService::class);

    $service->grant($tenant, $feature);
    $service->revoke($tenant, $feature);

    $row = TenantEntitlement::query()->where('tenant_id', $tenant->getKey())->firstOrFail();

    expect($service->hasFeature($tenant, 'booking.queue'))->toBeFalse()
        ->and($row->status)->toBe(EntitlementStatus::Inactive);
});

test('regrant reactivates an existing entitlement idempotently', function () {
    $tenant = Tenant::factory()->create();
    $module = makeActiveModule('booking');
    $feature = makeActiveFeature($module, 'booking.queue');
    $service = app(EntitlementService::class);

    $first = $service->grant($tenant, $feature);
    $service->revoke($tenant, $feature);
    $second = $service->grant($tenant, $feature);

    expect($second->getKey())->toBe($first->getKey())
        ->and($service->hasFeature($tenant, 'booking.queue'))->toBeTrue()
        ->and(TenantEntitlement::query()->where('tenant_id', $tenant->getKey())->count())->toBe(1);
});

test('projection rebuild is idempotent and removes stale capabilities', function () {
    $tenant = Tenant::factory()->create();
    $module = makeActiveModule('booking');
    $queue = makeActiveFeature($module, 'booking.queue');
    $services = makeActiveFeature($module, 'booking.services');
    $service = app(EntitlementService::class);

    $service->grant($tenant, $queue);

    $service->rebuildProjection($tenant, [
        [
            'item' => $services,
            'source' => EntitlementSource::Subscription,
            'quantity' => 10,
        ],
    ]);

    expect($service->hasFeature($tenant, 'booking.queue'))->toBeFalse()
        ->and($service->hasFeature($tenant, 'booking.services'))->toBeTrue()
        ->and(TenantEntitlement::query()->where('tenant_id', $tenant->getKey())->count())->toBe(1);

    $service->rebuildProjection($tenant, [
        [
            'item' => $services,
            'source' => EntitlementSource::Subscription,
            'quantity' => 10,
        ],
    ]);

    expect(TenantEntitlement::query()->where('tenant_id', $tenant->getKey())->count())->toBe(1);
});

test('limit returns the entitlement quantity for the capability', function () {
    $tenant = Tenant::factory()->create();
    $module = makeActiveModule('booking');
    $feature = makeActiveFeature($module, 'booking.queue');

    app(EntitlementService::class)->grant($tenant, $feature, [
        'quantity' => 25,
    ]);

    expect(app(EntitlementService::class)->limit($tenant, 'booking.queue'))->toBe(25);
});

test('core modules are implicitly available and do not create tenant entitlement rows', function () {
    $tenant = Tenant::factory()->create();
    $core = Module::factory()->create([
        'key' => 'platform.core',
        'name' => 'Platform Core',
        'status' => 'active',
        'is_core' => true,
    ]);

    $service = app(EntitlementService::class);

    expect($service->hasModule($tenant, 'platform.core'))->toBeTrue();

    $entitlements = TenantEntitlement::query()
        ->where('tenant_id', $tenant->getKey())
        ->count();

    expect($entitlements)->toBe(0);
});

test('entitlement middleware allows active capability and denies it when revoked', function () {
    $tenant = Tenant::factory()->create();
    $module = makeActiveModule('booking');
    $feature = makeActiveFeature($module, 'booking.queue');
    $service = app(EntitlementService::class);
    $context = app(TenantContext::class);

    Route::get('/__entitlement-test', fn () => response('ok'))
        ->middleware('entitled:booking.queue');

    $context->set($tenant);

    $service->grant($tenant, $feature);

    $this->get('/__entitlement-test')->assertOk();

    $service->revoke($tenant, $feature);

    $this->get('/__entitlement-test')->assertForbidden();

    $context->clear();
});
