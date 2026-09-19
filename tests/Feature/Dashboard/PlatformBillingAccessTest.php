<?php

use App\Application\Authorization\TenantRbacBootstrapper;
use App\Application\Billing\PlatformBillingDashboardService;
use App\Application\Billing\PaymentService;
use App\Application\Billing\SubscriptionService;
use App\Domain\Billing\RefundStatus;
use App\Models\PlatformCredit;
use App\Models\PlatformInvoice;
use App\Models\PlatformPayment;
use App\Models\PlatformRefund;
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
use Tests\Support\FakeTenantPaymentGateway;
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


test('owner can create a platform billing checkout and reuse the same pending session', function (): void {
    $path = billingDashboardTenantDatabasePath();
    $this->billingDashboardTenantDatabasePath = $path;

    $tenant = createBillingDashboardTenant($path);
    $owner = addBillingDashboardMember($tenant);

    $feature = billingCatalogItem();
    $subscription = app(SubscriptionService::class)->create($tenant, [
        ['item' => $feature],
    ], 'monthly', 0);

    $invoice = $subscription->invoices()->latest('issued_at')->firstOrFail();

    config([
        'velora.payments.platform_provider' => 'fake',
        'velora.payments.drivers.fake' => FakeTenantPaymentGateway::class,
    ]);
    FakeTenantPaymentGateway::reset();

    $response = $this->actingAs($owner)
        ->post('http://'.$tenant->domains()->firstOrFail()->domain.'/dashboard/billing/invoices/'.$invoice->getKey().'/checkout');

    $response->assertRedirect('/dashboard')
        ->assertSessionHas('status', 'Platform billing checkout created successfully.')
        ->assertSessionHas('platform_billing_checkout_url', 'https://pay.example.test/session/1');

    $payment = PlatformPayment::query()
        ->where('tenant_id', $tenant->getKey())
        ->where('invoice_id', $invoice->getKey())
        ->where('status', 'pending')
        ->firstOrFail();

    expect($payment->provider)->toBe('fake')
        ->and($payment->metadata['checkout']['checkout_url'])->toBe('https://pay.example.test/session/1')
        ->and(PlatformPayment::query()->where('invoice_id', $invoice->getKey())->where('status', 'pending')->count())->toBe(1);

    $second = $this->actingAs($owner)
        ->post('http://'.$tenant->domains()->firstOrFail()->domain.'/dashboard/billing/invoices/'.$invoice->getKey().'/checkout');

    $second->assertRedirect('/dashboard')
        ->assertSessionHas('platform_billing_checkout_url', 'https://pay.example.test/session/1');

    expect(collect(FakeTenantPaymentGateway::$calls)->where('type', 'checkout')->count())->toBe(1)
        ->and(PlatformPayment::query()->where('invoice_id', $invoice->getKey())->where('status', 'pending')->count())->toBe(1);
});

test('owner can void an open platform invoice', function (): void {
    $path = billingDashboardTenantDatabasePath();
    $this->billingDashboardTenantDatabasePath = $path;

    $tenant = createBillingDashboardTenant($path);
    $owner = addBillingDashboardMember($tenant);

    $feature = billingCatalogItem();
    $subscription = app(SubscriptionService::class)->create($tenant, [
        ['item' => $feature],
    ], 'monthly', 0);
    $invoice = $subscription->invoices()->latest('issued_at')->firstOrFail();

    $this->actingAs($owner)
        ->post('http://'.$tenant->domains()->firstOrFail()->domain.'/dashboard/billing/invoices/'.$invoice->getKey().'/void', [
            'reason' => 'commercial adjustment',
        ])
        ->assertRedirect('/dashboard')
        ->assertSessionHas('status', 'Platform invoice voided successfully.');

    expect($invoice->refresh()->status->value)->toBe('void');
});

test('owner can refund a succeeded platform payment and reuse its idempotency key', function (): void {
    $path = billingDashboardTenantDatabasePath();
    $this->billingDashboardTenantDatabasePath = $path;

    $tenant = createBillingDashboardTenant($path);
    $owner = addBillingDashboardMember($tenant);

    $feature = billingCatalogItem();
    $subscription = app(SubscriptionService::class)->create($tenant, [
        ['item' => $feature],
    ], 'monthly', 0);
    $invoice = $subscription->invoices()->latest('issued_at')->firstOrFail();

    $payment = app(PaymentService::class)->createPending($invoice, 'fake', 'PAY-PLATFORM-1', 'EVENT-1');
    $payment->update([
        'metadata' => [
            'checkout' => [
                'provider_order_id' => 'FAKE-ORDER-1',
            ],
        ],
    ]);
    app(PaymentService::class)->markSucceeded($payment);

    config([
        'velora.payments.platform_provider' => 'fake',
        'velora.payments.drivers.fake' => FakeTenantPaymentGateway::class,
    ]);
    FakeTenantPaymentGateway::reset();

    $host = $tenant->domains()->firstOrFail()->domain;

    $this->actingAs($owner)
        ->post('http://'.$host.'/dashboard/billing/payments/'.$payment->getKey().'/refund', [
            'amount_minor' => 2000,
            'reason' => 'customer request',
            'idempotency_key' => 'refund-key-1',
        ])
        ->assertRedirect('/dashboard')
        ->assertSessionHas('status', 'Platform payment refund processed successfully.');

    $refund = PlatformRefund::query()
        ->where('payment_id', $payment->getKey())
        ->firstOrFail();

    expect($refund->status)->toBe(RefundStatus::Succeeded)
        ->and($refund->idempotency_key)->toBe('refund-key-1')
        ->and($refund->initiated_by_account_id)->toBe($owner->getKey())
        ->and(collect(FakeTenantPaymentGateway::$calls)->where('type', 'refund')->count())->toBe(1);

    $this->actingAs($owner)
        ->post('http://'.$host.'/dashboard/billing/payments/'.$payment->getKey().'/refund', [
            'amount_minor' => 2000,
            'reason' => 'customer request',
            'idempotency_key' => 'refund-key-1',
        ])
        ->assertRedirect('/dashboard');

    expect(PlatformRefund::query()->where('payment_id', $payment->getKey())->count())->toBe(1)
        ->and(collect(FakeTenantPaymentGateway::$calls)->where('type', 'refund')->count())->toBe(1);
});

test('owner cannot refund beyond the remaining platform payment amount', function (): void {
    $path = billingDashboardTenantDatabasePath();
    $this->billingDashboardTenantDatabasePath = $path;

    $tenant = createBillingDashboardTenant($path);
    $owner = addBillingDashboardMember($tenant);

    $feature = billingCatalogItem();
    $subscription = app(SubscriptionService::class)->create($tenant, [
        ['item' => $feature],
    ], 'monthly', 0);
    $invoice = $subscription->invoices()->latest('issued_at')->firstOrFail();
    $payment = app(PaymentService::class)->createPending($invoice);
    app(PaymentService::class)->markSucceeded($payment);

    $this->actingAs($owner)
        ->post('http://'.$tenant->domains()->firstOrFail()->domain.'/dashboard/billing/payments/'.$payment->getKey().'/refund', [
            'amount_minor' => 6000,
            'reason' => 'too much',
        ])
        ->assertSessionHasErrors('billing');
});

test('owner can issue a platform credit and it appears in the billing overview', function (): void {
    $path = billingDashboardTenantDatabasePath();
    $this->billingDashboardTenantDatabasePath = $path;

    $tenant = createBillingDashboardTenant($path);
    $owner = addBillingDashboardMember($tenant);

    $context = app(\App\Domain\Tenancy\TenantContext::class);
    $context->set($tenant);

    $this->actingAs($owner)
        ->post('http://'.$tenant->domains()->firstOrFail()->domain.'/dashboard/billing/credits', [
            'amount_minor' => 2500,
            'currency' => 'EGP',
            'source' => 'service recovery',
        ])
        ->assertRedirect('/dashboard')
        ->assertSessionHas('status', 'Platform credit issued successfully.');

    $overview = app(PlatformBillingDashboardService::class)->overview($tenant);

    expect($overview['credits'])->toHaveCount(1)
        ->and($overview['credits']->first()->amount_minor)->toBe(2500)
        ->and(PlatformCredit::query()->where('tenant_id', $tenant->getKey())->count())->toBe(1);
});

test('viewer cannot refund a platform payment or issue a platform credit', function (): void {
    $path = billingDashboardTenantDatabasePath();
    $this->billingDashboardTenantDatabasePath = $path;

    $tenant = createBillingDashboardTenant($path);
    $viewer = addBillingDashboardMember($tenant, 'viewer');

    $feature = billingCatalogItem();
    $subscription = app(SubscriptionService::class)->create($tenant, [
        ['item' => $feature],
    ], 'monthly', 0);
    $invoice = $subscription->invoices()->latest('issued_at')->firstOrFail();
    $payment = app(PaymentService::class)->createPending($invoice);
    app(PaymentService::class)->markSucceeded($payment);

    $host = $tenant->domains()->firstOrFail()->domain;

    $this->actingAs($viewer)
        ->post('http://'.$host.'/dashboard/billing/payments/'.$payment->getKey().'/refund', [
            'amount_minor' => 1000,
            'reason' => 'not allowed',
        ])
        ->assertForbidden();

    $this->actingAs($viewer)
        ->post('http://'.$host.'/dashboard/billing/credits', [
            'amount_minor' => 1000,
            'currency' => 'EGP',
        ])
        ->assertForbidden();
});

test('viewer cannot create a platform billing checkout', function (): void {
    $path = billingDashboardTenantDatabasePath();
    $this->billingDashboardTenantDatabasePath = $path;

    $tenant = createBillingDashboardTenant($path);
    $viewer = addBillingDashboardMember($tenant, 'viewer');

    $feature = billingCatalogItem();
    $subscription = app(SubscriptionService::class)->create($tenant, [
        ['item' => $feature],
    ], 'monthly', 0);
    $invoice = $subscription->invoices()->latest('issued_at')->firstOrFail();

    $this->actingAs($viewer)
        ->post('http://'.$tenant->domains()->firstOrFail()->domain.'/dashboard/billing/invoices/'.$invoice->getKey().'/checkout')
        ->assertForbidden();
});
