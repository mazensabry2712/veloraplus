<?php

use App\Application\Authorization\TenantRbacBootstrapper;
use App\Application\Billing\PlatformBillingDashboardService;
use App\Application\Billing\SubscriptionService;
use App\Application\Entitlements\EntitlementService;
use App\Infrastructure\Tenancy\TenantDatabaseManager;
use App\Models\CatalogPrice;
use App\Models\Feature;
use App\Models\PlatformAccount;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TenantMembership;
use App\Models\Module;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->originalTenantTemplate = config('database.connections.tenant_template');
});

function billingDashboardTenantDatabasePath(): string
{
    $directory = storage_path('framework/testing');

    File::ensureDirectoryExists($directory);

    $path = $directory.'/dashboard-billing-'.Str::ulid().'.sqlite';
    touch($path);

    config([
        'database.connections.tenant_template' => [
            'driver' => 'sqlite',
            'url' => null,
            'database' => $path,
            'prefix' => '',
            'foreign_key_constraints' => true,
            'busy_timeout' => 5000,
            'journal_mode' => null,
            'synchronous' => null,
            'transaction_mode' => 'DEFERRED',
        ],
    ]);

    return $path;
}

function createBillingDashboardTenant(
    string $path,
    string $domain = 'billing-dashboard.velora.test',
    string $slug = 'billing-dashboard',
): Tenant {
    $tenant = Tenant::factory()->create([
        'name' => 'Billing Dashboard',
        'slug' => $slug,
        'database_name' => $path,
        'database_host' => null,
        'database_port' => null,
        'status' => 'active',
        'database_status' => 'ready',
    ]);

    TenantDomain::create([
        'tenant_id' => $tenant->getKey(),
        'domain' => $domain,
        'type' => 'subdomain',
        'is_primary' => true,
        'status' => 'active',
        'verified_at' => now(),
    ]);

    $manager = app(TenantDatabaseManager::class);
    $manager->connect($tenant);

    try {
        expect(Artisan::call('migrate', [
            '--database' => TenantDatabaseManager::CONNECTION,
            '--path' => 'database/migrations/tenant',
            '--force' => true,
        ]))->toBe(0);
    } finally {
        $manager->disconnect();
    }

    return $tenant;
}

function addBillingDashboardMember(Tenant $tenant, string $role = 'owner'): PlatformAccount
{
    $account = PlatformAccount::factory()->create();

    TenantMembership::create([
        'tenant_id' => $tenant->getKey(),
        'account_id' => $account->getKey(),
        'role_key' => $role,
        'status' => 'active',
        'joined_at' => now(),
    ]);

    app(TenantRbacBootstrapper::class)->bootstrapForTenant($tenant);

    return $account;
}

function billingCatalogItem(): Feature
{
    $module = Module::factory()->create([
        'key' => 'billing-test-module-'.Str::lower(Str::random(6)),
        'name' => 'Billing Test Module',
        'status' => 'active',
    ]);

    $feature = Feature::factory()->create([
        'module_id' => $module->getKey(),
        'key' => 'billing.test.feature-'.Str::lower(Str::random(6)),
        'name' => 'Billing Test Feature',
        'status' => 'active',
        'is_individually_purchasable' => true,
    ]);

    app(\App\Application\Catalog\CatalogManager::class)->addPrice($feature, [
        'billing_cycle' => 'monthly',
        'currency' => 'EGP',
        'amount_minor' => 5000,
        'effective_from' => now()->subMinute(),
    ]);

    return $feature;
}

function paidBillingDashboardSubscription(Tenant $tenant): Subscription
{
    $feature = billingCatalogItem();

    $subscription = app(SubscriptionService::class)->create($tenant, [
        ['item' => $feature],
    ], 'monthly', 0);

    $invoice = $subscription->invoices()->latest('issued_at')->firstOrFail();
    $payment = app(\App\Application\Billing\PaymentService::class)->createPending($invoice);
    app(\App\Application\Billing\PaymentService::class)->markSucceeded($payment);

    return $subscription->fresh('items');
}

afterEach(function (): void {
    DB::purge(TenantDatabaseManager::CONNECTION);
    DB::setDefaultConnection('central');
    app(\App\Domain\Tenancy\TenantContext::class)->clear();
    setPermissionsTeamId(null);

    config([
        'database.connections.tenant_template' => $this->originalTenantTemplate,
    ]);

    if (isset($this->billingDashboardTenantDatabasePath) && is_file($this->billingDashboardTenantDatabasePath)) {
        unlink($this->billingDashboardTenantDatabasePath);
    }
});

test('owner can read billing overview and schedule subscription cancellation', function (): void {
    $path = billingDashboardTenantDatabasePath();
    $this->billingDashboardTenantDatabasePath = $path;

    $tenant = createBillingDashboardTenant($path);
    $owner = addBillingDashboardMember($tenant);
    $subscription = paidBillingDashboardSubscription($tenant);

    $context = app(\App\Domain\Tenancy\TenantContext::class);
    $context->set($tenant);

    $overview = app(PlatformBillingDashboardService::class)->overview($tenant);

    expect($overview['subscription']->getKey())->toBe($subscription->getKey())
        ->and($overview['invoices'])->toHaveCount(1)
        ->and($overview['payments'])->toHaveCount(1);

    $this->actingAs($owner)
        ->post('http://billing-dashboard.velora.test/dashboard/billing/subscription/'.$subscription->getKey().'/cancel')
        ->assertRedirect('/dashboard')
        ->assertSessionHas('status', 'Subscription cancellation scheduled successfully.');

    expect($subscription->refresh()->cancel_at_period_end)->toBeTrue();
});

test('viewer can view billing but cannot manage the subscription', function (): void {
    $path = billingDashboardTenantDatabasePath();
    $this->billingDashboardTenantDatabasePath = $path;

    $tenant = createBillingDashboardTenant($path);
    $viewer = addBillingDashboardMember($tenant, 'viewer');
    $subscription = paidBillingDashboardSubscription($tenant);

    $this->actingAs($viewer)
        ->post('http://billing-dashboard.velora.test/dashboard/billing/subscription/'.$subscription->getKey().'/cancel')
        ->assertForbidden();
});

test('staff cannot access platform billing management', function (): void {
    $path = billingDashboardTenantDatabasePath();
    $this->billingDashboardTenantDatabasePath = $path;

    $tenant = createBillingDashboardTenant($path);
    $staff = addBillingDashboardMember($tenant, 'staff');
    $subscription = paidBillingDashboardSubscription($tenant);

    $this->actingAs($staff)
        ->post('http://billing-dashboard.velora.test/dashboard/billing/subscription/'.$subscription->getKey().'/cancel')
        ->assertForbidden();
});

test('billing policy denies access to a subscription belonging to another tenant', function (): void {
    $pathA = billingDashboardTenantDatabasePath();
    $pathB = billingDashboardTenantDatabasePath();
    $this->billingDashboardTenantDatabasePath = $pathA;

    $tenantA = createBillingDashboardTenant($pathA, 'billing-a.velora.test', 'billing-a');
    $tenantB = createBillingDashboardTenant($pathB, 'billing-b.velora.test', 'billing-b');
    $ownerA = addBillingDashboardMember($tenantA);
    $subscriptionB = paidBillingDashboardSubscription($tenantB);

    $this->actingAs($ownerA)
        ->post('http://billing-a.velora.test/dashboard/billing/subscription/'.$subscriptionB->getKey().'/cancel')
        ->assertForbidden();

    DB::purge(TenantDatabaseManager::CONNECTION);
    if (is_file($pathB)) {
        unlink($pathB);
    }
});
