<?php

use App\Application\Authorization\TenantRbacBootstrapper;
use App\Application\Booking\AppointmentManager;
use App\Application\Booking\StaffAvailabilityManager;
use App\Application\Payments\TenantPaymentManager;
use App\Domain\Booking\AppointmentPaymentStatus;
use App\Domain\Payments\TenantPaymentStatus;
use App\Infrastructure\Tenancy\TenantDatabaseManager;
use App\Models\Appointment;
use App\Models\Feature;
use App\Models\Location;
use App\Models\Module;
use App\Models\PaymentProviderAccount;
use App\Models\PlatformAccount;
use App\Models\Service;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TenantMembership;
use App\Models\TenantPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\Support\FakeTenantPaymentGateway;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->originalTenantTemplate = config('database.connections.tenant_template');

    FakeTenantPaymentGateway::reset();

    config([
        'velora.payments.tenant_provider' => 'fake',
        'velora.payments.drivers.fake' => FakeTenantPaymentGateway::class,
    ]);
});

function dashboardTenantPaymentDatabasePath(): string
{
    $directory = storage_path('framework/testing');

    File::ensureDirectoryExists($directory);

    $path = $directory.'/dashboard-tenant-payments-'.Str::ulid().'.sqlite';
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

function createDashboardTenantPaymentTenant(
    string $path,
    string $domain = 'tenant-payments.velora.test',
    string $slug = 'tenant-payments',
): Tenant {
    $tenant = Tenant::factory()->create([
        'name' => 'Tenant Payments',
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

function addDashboardTenantPaymentMember(Tenant $tenant, string $roleKey = 'owner'): PlatformAccount
{
    $account = PlatformAccount::factory()->create();

    TenantMembership::create([
        'tenant_id' => $tenant->getKey(),
        'account_id' => $account->getKey(),
        'role_key' => $roleKey,
        'status' => 'active',
        'joined_at' => now(),
    ]);

    app(TenantRbacBootstrapper::class)->bootstrapForTenant($tenant);

    return $account;
}

function enableDashboardTenantPaymentFeature(Tenant $tenant, ?Feature $feature = null): Feature
{
    if ($feature === null) {
        $module = Module::factory()->create([
            'key' => 'booking',
            'name' => 'Booking',
            'status' => 'active',
        ]);

        $feature = Feature::factory()->create([
            'module_id' => $module->getKey(),
            'key' => 'booking.payments',
            'name' => 'Booking Payments',
            'status' => 'active',
        ]);
    }

    app(\App\Application\Entitlements\EntitlementService::class)->grant($tenant, $feature);

    return $feature;
}

function dashboardTenantPaymentFixtures(Tenant $tenant): array
{
    $manager = app(TenantDatabaseManager::class);
    $manager->connect($tenant);

    try {
        $location = Location::query()->create([
            'name' => 'Main Branch',
            'code' => 'PAY',
            'country_code' => 'EG',
            'city' => 'Cairo',
            'timezone' => 'Africa/Cairo',
            'status' => 'active',
            'metadata' => [],
        ]);

        $staff = Staff::query()->create([
            'location_id' => $location->getKey(),
            'name' => 'Dr. Payment',
            'email' => 'payment@example.test',
            'status' => 'active',
            'metadata' => [],
        ]);

        $customer = \App\Models\Customer::query()->create([
            'name' => 'Paying Customer',
            'phone' => '+201000000000',
            'email' => 'payer@example.test',
            'status' => 'active',
            'source' => 'dashboard',
            'metadata' => [],
        ]);

        $service = Service::query()->create([
            'name' => 'Paid Consultation',
            'duration_minutes' => 60,
            'buffer_before_minutes' => 0,
            'buffer_after_minutes' => 0,
            'price_minor' => 50000,
            'currency' => 'EGP',
            'deposit_amount_minor' => 0,
            'status' => 'active',
            'online_bookable' => true,
            'capacity' => 1,
            'metadata' => [],
        ]);

        app(StaffAvailabilityManager::class)->assignService($staff, $service);
        app(StaffAvailabilityManager::class)->saveWorkingHour($staff, 1, '09:00', '17:00');

        $appointment = app(AppointmentManager::class)->create(
            customer: $customer,
            staff: $staff,
            service: $service,
            startsAt: '2026-09-21 10:00:00+03:00',
            attributes: ['idempotency_key' => 'dashboard-payment-'.Str::ulid()],
        );

        return compact('location', 'staff', 'customer', 'service', 'appointment');
    } finally {
        $manager->disconnect();
    }
}

function dashboardTenantPaymentAccount(Tenant $tenant): PaymentProviderAccount
{
    return PaymentProviderAccount::query()->create([
        'tenant_id' => $tenant->getKey(),
        'provider' => 'fake',
        'account_reference' => 'FAKE-DASHBOARD',
        'status' => 'active',
        'encrypted_credentials' => [
            'merchant_id' => 'MID-DASHBOARD',
        ],
    ]);
}

function dashboardTenantPaymentContext(Tenant $tenant): void
{
    app(\App\Domain\Tenancy\TenantContext::class)->set($tenant);
    app(TenantDatabaseManager::class)->connect($tenant);
}

afterEach(function (): void {
    DB::purge(TenantDatabaseManager::CONNECTION);
    DB::setDefaultConnection('central');
    app(\App\Domain\Tenancy\TenantContext::class)->clear();
    setPermissionsTeamId(null);
    FakeTenantPaymentGateway::reset();

    config([
        'database.connections.tenant_template' => $this->originalTenantTemplate,
    ]);

    if (isset($this->dashboardTenantPaymentDatabasePath) && is_file($this->dashboardTenantPaymentDatabasePath)) {
        unlink($this->dashboardTenantPaymentDatabasePath);
    }
});

test('owner can create a tenant payment checkout without creating platform billing records', function (): void {
    $path = dashboardTenantPaymentDatabasePath();
    $this->dashboardTenantPaymentDatabasePath = $path;

    $tenant = createDashboardTenantPaymentTenant($path);
    $owner = addDashboardTenantPaymentMember($tenant);
    enableDashboardTenantPaymentFeature($tenant);
    dashboardTenantPaymentAccount($tenant);

    $this->actingAs($owner)
        ->post('http://tenant-payments.velora.test/dashboard/booking/payments/appointments/placeholder')
        ->assertNotFound();

    $fixtures = dashboardTenantPaymentFixtures($tenant);

    $this->actingAs($owner)
        ->post('http://tenant-payments.velora.test/dashboard/booking/payments/appointments/'.$fixtures['appointment']->getKey())
        ->assertRedirect('/dashboard')
        ->assertSessionHas('status', 'Tenant payment checkout created successfully.')
        ->assertSessionHas('tenant_payment_checkout_url', 'https://pay.example.test/session/1');

    dashboardTenantPaymentContext($tenant);

    try {
        $payment = TenantPayment::query()->firstOrFail();

        expect($payment->status)->toBe(TenantPaymentStatus::Pending)
            ->and($fixtures['appointment']->refresh()->payment_status)->toBe(AppointmentPaymentStatus::Pending)
            ->and(FakeTenantPaymentGateway::$calls)->toHaveCount(1)
            ->and(DB::connection('central')->table('platform_payments')->count())->toBe(0);
    } finally {
        app(TenantDatabaseManager::class)->disconnect();
    }
});

test('viewer cannot create tenant payment checkout', function (): void {
    $path = dashboardTenantPaymentDatabasePath();
    $this->dashboardTenantPaymentDatabasePath = $path;

    $tenant = createDashboardTenantPaymentTenant($path);
    $viewer = addDashboardTenantPaymentMember($tenant, 'viewer');
    enableDashboardTenantPaymentFeature($tenant);
    dashboardTenantPaymentAccount($tenant);

    $fixtures = dashboardTenantPaymentFixtures($tenant);

    $this->actingAs($viewer)
        ->post('http://tenant-payments.velora.test/dashboard/booking/payments/appointments/'.$fixtures['appointment']->getKey())
        ->assertForbidden();
});

test('tenant payment entitlement is required for checkout', function (): void {
    $path = dashboardTenantPaymentDatabasePath();
    $this->dashboardTenantPaymentDatabasePath = $path;

    $tenant = createDashboardTenantPaymentTenant($path);
    $owner = addDashboardTenantPaymentMember($tenant);
    dashboardTenantPaymentAccount($tenant);

    $fixtures = dashboardTenantPaymentFixtures($tenant);

    $this->actingAs($owner)
        ->post('http://tenant-payments.velora.test/dashboard/booking/payments/appointments/'.$fixtures['appointment']->getKey())
        ->assertForbidden();
});

test('owner can reconcile a pending tenant payment and refund a succeeded payment', function (): void {
    $path = dashboardTenantPaymentDatabasePath();
    $this->dashboardTenantPaymentDatabasePath = $path;

    $tenant = createDashboardTenantPaymentTenant($path);
    $owner = addDashboardTenantPaymentMember($tenant);
    enableDashboardTenantPaymentFeature($tenant);
    dashboardTenantPaymentAccount($tenant);

    $fixtures = dashboardTenantPaymentFixtures($tenant);

    dashboardTenantPaymentContext($tenant);

    try {
        $payment = app(TenantPaymentManager::class)->createCheckout($fixtures['appointment']);
        FakeTenantPaymentGateway::$transactionStatus = 'SUCCESS';
        FakeTenantPaymentGateway::$transactionAmount = '500.00';
        FakeTenantPaymentGateway::$transactionCurrency = 'EGP';
        FakeTenantPaymentGateway::$transactionId = 'RECON-TX';
        FakeTenantPaymentGateway::$transactionOrderId = 'RECON-ORDER';
    } finally {
        app(TenantDatabaseManager::class)->disconnect();
        app(\App\Domain\Tenancy\TenantContext::class)->clear();
    }

    $this->actingAs($owner)
        ->post('http://tenant-payments.velora.test/dashboard/booking/payments/'.$payment->getKey().'/reconcile')
        ->assertRedirect('/dashboard');

    $this->actingAs($owner)
        ->post('http://tenant-payments.velora.test/dashboard/booking/payments/'.$payment->getKey().'/refund', [
            'amount_minor' => 50000,
            'reason' => 'Customer cancellation',
            'idempotency_key' => 'refund-dashboard-1',
        ])
        ->assertRedirect('/dashboard')
        ->assertSessionHas('status', 'Tenant payment refund requested successfully.');

    dashboardTenantPaymentContext($tenant);

    try {
        $payment = $payment->fresh();
        expect($payment->status)->toBe(TenantPaymentStatus::Refunded)
            ->and($payment->appointment()->first()->payment_status)->toBe(AppointmentPaymentStatus::Refunded)
            ->and($payment->refunds()->count())->toBe(1)
            ->and($payment->refunds()->first()->amount_minor)->toBe(50000);
    } finally {
        app(TenantDatabaseManager::class)->disconnect();
    }
});

test('refund validation prevents non-positive and over-refund amounts', function (): void {
    $path = dashboardTenantPaymentDatabasePath();
    $this->dashboardTenantPaymentDatabasePath = $path;

    $tenant = createDashboardTenantPaymentTenant($path);
    $owner = addDashboardTenantPaymentMember($tenant);
    enableDashboardTenantPaymentFeature($tenant);
    dashboardTenantPaymentAccount($tenant);

    dashboardTenantPaymentContext($tenant);
    $fixtures = dashboardTenantPaymentFixtures($tenant);
    $payment = app(TenantPaymentManager::class)->createCheckout($fixtures['appointment']);
    app(TenantPaymentManager::class)->markSucceeded($payment, 'TX-REFUND', 'ORDER-REFUND');

    app(TenantDatabaseManager::class)->disconnect();
    app(\App\Domain\Tenancy\TenantContext::class)->clear();

    $this->actingAs($owner)
        ->post('http://tenant-payments.velora.test/dashboard/booking/payments/'.$payment->getKey().'/refund', [
            'amount_minor' => 0,
            'reason' => 'Invalid',
        ])
        ->assertSessionHasErrors('amount_minor');

    $this->actingAs($owner)
        ->post('http://tenant-payments.velora.test/dashboard/booking/payments/'.$payment->getKey().'/refund', [
            'amount_minor' => 60000,
            'reason' => 'Too much',
        ])
        ->assertRedirect('/dashboard');

    dashboardTenantPaymentContext($tenant);

    try {
        expect($payment->refresh()->refunds()->count())->toBe(0);
    } finally {
        app(TenantDatabaseManager::class)->disconnect();
    }
});
