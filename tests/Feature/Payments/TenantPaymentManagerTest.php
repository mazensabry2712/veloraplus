<?php

use App\Application\Booking\AppointmentManager;
use App\Application\Booking\StaffAvailabilityManager;
use App\Application\Payments\TenantPaymentManager;
use App\Domain\Booking\AppointmentPaymentStatus;
use App\Domain\Payments\TenantPaymentStatus;
use App\Domain\Tenancy\TenantContext;
use App\Infrastructure\Tenancy\TenantDatabaseManager;
use App\Models\Customer;
use App\Models\Location;
use App\Models\PaymentProviderAccount;
use App\Models\Service;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\TenantPayment;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Support\FakeTenantPaymentGateway;

beforeEach(function (): void {
    $this->originalTenantTemplate = config('database.connections.tenant_template');
    DB::setDefaultConnection('central');
    expect(Artisan::call('migrate:fresh', [
        '--database' => 'central',
        '--force' => true,
    ]))->toBe(0);
    FakeTenantPaymentGateway::reset();
});

afterEach(function (): void {
    DB::purge(TenantDatabaseManager::CONNECTION);
    DB::setDefaultConnection('central');
    app(TenantContext::class)->clear();
    config(['database.connections.tenant_template' => $this->originalTenantTemplate]);

    if (isset($this->tenantPaymentTestDatabases)) {
        foreach ($this->tenantPaymentTestDatabases as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
});

function tenantPaymentDatabase(): string
{
    $directory = storage_path('framework/testing');

    File::ensureDirectoryExists($directory);

    $path = $directory.'/tenant-payment-'.Str::ulid().'.sqlite';

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

function migrateTenantPaymentDatabase(string $path): void
{
    config(['database.connections.tenant_template.database' => $path]);

    $tenant = new Tenant;
    $tenant->database_name = $path;

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
}

function tenantPaymentFixtures(): array
{
    $location = Location::factory()->create([
        'timezone' => 'Africa/Cairo',
        'status' => 'active',
    ]);

    $staff = Staff::factory()->forLocation($location)->create([
        'status' => 'active',
    ]);

    $customer = Customer::factory()->create([
        'status' => 'active',
    ]);

    $service = Service::factory()->create([
        'duration_minutes' => 60,
        'buffer_before_minutes' => 10,
        'buffer_after_minutes' => 10,
        'price_minor' => 50000,
        'currency' => 'EGP',
        'status' => 'active',
        'online_bookable' => true,
    ]);

    app(StaffAvailabilityManager::class)->assignService($staff, $service);
    app(StaffAvailabilityManager::class)->saveWorkingHour($staff, 1, '09:00', '17:00');

    $appointment = app(AppointmentManager::class)->create(
        customer: $customer,
        staff: $staff,
        service: $service,
        startsAt: '2026-09-21 10:00:00+03:00',
        attributes: ['idempotency_key' => 'payment-'.Str::ulid()],
    );

    return compact('location', 'staff', 'customer', 'service', 'appointment');
}

function configureFakeTenantGateway(): void
{
    config([
        'velora.payments.tenant_provider' => 'fake',
        'velora.payments.drivers.fake' => FakeTenantPaymentGateway::class,
    ]);

    FakeTenantPaymentGateway::reset();
}

function tenantPaymentContext(Tenant $tenant, string $path): void
{
    $tenant->database_name = $path;

    app(TenantContext::class)->set($tenant);

    $manager = app(TenantDatabaseManager::class);
    $manager->connect($tenant);
}

test('tenant payment migrations create the tenant ledger without touching platform payments', function (): void {
    $path = tenantPaymentDatabase();
    $this->tenantPaymentTestDatabases = [$path];

    migrateTenantPaymentDatabase($path);

    expect(Schema::connection(TenantDatabaseManager::CONNECTION)->hasTable('tenant_payments'))->toBeTrue()
        ->and(Schema::connection(TenantDatabaseManager::CONNECTION)->hasColumn('tenant_payments', 'merchant_order_id'))->toBeTrue()
        ->and(Schema::connection(TenantDatabaseManager::CONNECTION)->hasColumn('tenant_payments', 'amount_minor'))->toBeTrue()
        ->and(Schema::connection(TenantDatabaseManager::CONNECTION)->hasTable('platform_payments'))->toBeFalse();
});

test('tenant payment provider accounts are stored centrally with encrypted credentials', function (): void {
    $tenant = Tenant::factory()->create();

    $account = PaymentProviderAccount::query()->create([
        'tenant_id' => $tenant->getKey(),
        'provider' => 'kashier',
        'account_reference' => 'KASHIER-CLINIC',
        'status' => 'active',
        'encrypted_credentials' => [
            'merchant_id' => 'MID-CLINIC',
            'secret_key' => 'SECRET-CLINIC',
            'payment_api_key' => 'API-CLINIC',
        ],
    ]);

    $raw = $account->getRawOriginal('encrypted_credentials');

    expect($raw)->not->toContain('SECRET-CLINIC')
        ->and($account->fresh()->encrypted_credentials['merchant_id'])->toBe('MID-CLINIC');
});


test('tenant payment checkout requires an active merchant account', function (): void {
    $path = tenantPaymentDatabase();
    $this->tenantPaymentTestDatabases = [$path];

    migrateTenantPaymentDatabase($path);

    $tenant = Tenant::factory()->create();
    tenantPaymentContext($tenant, $path);
    $fixtures = tenantPaymentFixtures();

    config(['velora.payments.tenant_provider' => 'fake']);

    configureFakeTenantGateway();

    expect(fn () => app(TenantPaymentManager::class)->createCheckout($fixtures['appointment']))
        ->toThrow(DomainException::class);

    expect(TenantPayment::query()->count())->toBe(0)
        ->and($fixtures['appointment']->fresh()->payment_status)->toBe(AppointmentPaymentStatus::Unpaid);
});


test('tenant payment rejects cancelled appointments before creating a payment', function (): void {
    $path = tenantPaymentDatabase();
    $this->tenantPaymentTestDatabases = [$path];

    migrateTenantPaymentDatabase($path);

    $tenant = Tenant::factory()->create();
    PaymentProviderAccount::query()->create([
        'tenant_id' => $tenant->getKey(),
        'provider' => 'fake',
        'account_reference' => 'FAKE-CLINIC',
        'status' => 'active',
    ]);

    tenantPaymentContext($tenant, $path);
    $fixtures = tenantPaymentFixtures($tenant);

    app(AppointmentManager::class)
        ->cancel($fixtures['appointment'], 'Cancelled before payment');

    $calls = [];
    configureFakeTenantGateway();

    expect(fn () => app(TenantPaymentManager::class)->createCheckout($fixtures['appointment']))
        ->toThrow(DomainException::class);

    expect(TenantPayment::query()->count())->toBe(0);
});

test('tenant payment cannot be marked succeeded from a failed state', function (): void {
    $path = tenantPaymentDatabase();
    $this->tenantPaymentTestDatabases = [$path];

    migrateTenantPaymentDatabase($path);

    $tenant = Tenant::factory()->create();
    PaymentProviderAccount::query()->create([
        'tenant_id' => $tenant->getKey(),
        'provider' => 'fake',
        'account_reference' => 'FAKE-CLINIC',
        'status' => 'active',
    ]);

    tenantPaymentContext($tenant, $path);
    $fixtures = tenantPaymentFixtures($tenant);

    $calls = [];
    configureFakeTenantGateway();

    $manager = app(TenantPaymentManager::class);
    $payment = $manager->createCheckout($fixtures['appointment']);
    $manager->markFailed($payment, 'Test failure');

    expect(fn () => $manager->markSucceeded($payment))
        ->toThrow(DomainException::class);

    expect($fixtures['appointment']->fresh()->payment_status)->toBe(AppointmentPaymentStatus::Failed);
});

test('tenant payment checkout is idempotent and updates appointment payment state', function (): void {
    $path = tenantPaymentDatabase();
    $this->tenantPaymentTestDatabases = [$path];

    migrateTenantPaymentDatabase($path);

    $tenant = Tenant::factory()->create();
    PaymentProviderAccount::query()->create([
        'tenant_id' => $tenant->getKey(),
        'provider' => 'fake',
        'account_reference' => 'FAKE-CLINIC',
        'status' => 'active',
        'encrypted_credentials' => ['merchant_id' => 'MID-FAKE'],
    ]);

    tenantPaymentContext($tenant, $path);
    $fixtures = tenantPaymentFixtures($tenant);

    $calls = [];
    configureFakeTenantGateway();

    $manager = app(TenantPaymentManager::class);

    $first = $manager->createCheckout($fixtures['appointment']);
    $second = $manager->createCheckout($fixtures['appointment']);

    expect($first->getKey())->toBe($second->getKey())
        ->and($first->status)->toBe(TenantPaymentStatus::Pending)
        ->and($first->amount_minor)->toBe(50000)
        ->and($first->currency)->toBe('EGP')
        ->and($first->provider_session_id)->toBe('SESSION-1')
        ->and($first->metadata['payment_provider_account_reference'])->toBe('FAKE-CLINIC')
        ->and(FakeTenantPaymentGateway::$calls)->toHaveCount(1)
        ->and(FakeTenantPaymentGateway::$calls[0]['payment_account']['credentials']['merchant_id'])->toBe('MID-FAKE')
        ->and($fixtures['appointment']->fresh()->payment_status)->toBe(AppointmentPaymentStatus::Pending)
        ->and(TenantPayment::query()->count())->toBe(1);
});

test('tenant payment checkout failure marks only the tenant payment as failed', function (): void {
    $path = tenantPaymentDatabase();
    $this->tenantPaymentTestDatabases = [$path];

    migrateTenantPaymentDatabase($path);

    $tenant = Tenant::factory()->create();
    PaymentProviderAccount::query()->create([
        'tenant_id' => $tenant->getKey(),
        'provider' => 'fake',
        'account_reference' => 'FAKE-CLINIC',
        'status' => 'active',
        'encrypted_credentials' => ['merchant_id' => 'MID-FAKE'],
    ]);

    tenantPaymentContext($tenant, $path);
    $fixtures = tenantPaymentFixtures($tenant);

    config(['velora.payments.tenant_provider' => 'fake']);

    config([
        'velora.payments.tenant_provider' => 'fake',
        'velora.payments.drivers.fake' => FakeTenantPaymentGateway::class,
    ]);
    FakeTenantPaymentGateway::reset();
    FakeTenantPaymentGateway::$shouldFail = true;

    $manager = app(TenantPaymentManager::class);

    expect(fn () => $manager->createCheckout($fixtures['appointment']))
        ->toThrow(RuntimeException::class);

    $payment = TenantPayment::query()->firstOrFail();

    expect($payment->status)->toBe(TenantPaymentStatus::Failed)
        ->and($payment->failed_at)->not->toBeNull()
        ->and($fixtures['appointment']->fresh()->payment_status)->toBe(AppointmentPaymentStatus::Failed);
});

test('verified tenant payment success is idempotent and marks the appointment paid', function (): void {
    $path = tenantPaymentDatabase();
    $this->tenantPaymentTestDatabases = [$path];

    migrateTenantPaymentDatabase($path);

    $tenant = Tenant::factory()->create();
    PaymentProviderAccount::query()->create([
        'tenant_id' => $tenant->getKey(),
        'provider' => 'fake',
        'account_reference' => 'FAKE-CLINIC',
        'status' => 'active',
        'encrypted_credentials' => ['merchant_id' => 'MID-FAKE'],
    ]);

    tenantPaymentContext($tenant, $path);
    $fixtures = tenantPaymentFixtures($tenant);

    $calls = [];
    configureFakeTenantGateway();

    $manager = app(TenantPaymentManager::class);
    $payment = $manager->createCheckout($fixtures['appointment']);

    $succeeded = $manager->markSucceeded(
        $payment,
        providerPaymentId: 'PROVIDER-TX-1',
        providerOrderId: 'PROVIDER-ORDER-1',
        metadata: ['verification_source' => 'test-webhook'],
    );

    $again = $manager->markSucceeded(
        $payment,
        providerPaymentId: 'PROVIDER-TX-1',
        providerOrderId: 'PROVIDER-ORDER-1',
    );

    expect($succeeded->status)->toBe(TenantPaymentStatus::Succeeded)
        ->and($succeeded->paid_at)->not->toBeNull()
        ->and($succeeded->provider_payment_id)->toBe('PROVIDER-TX-1')
        ->and($again->getKey())->toBe($succeeded->getKey())
        ->and($fixtures['appointment']->fresh()->payment_status)->toBe(AppointmentPaymentStatus::Paid)
        ->and(TenantPayment::query()->count())->toBe(1);
});

test('tenant payments cannot cross tenant database boundaries', function (): void {
    $pathA = tenantPaymentDatabase();
    $pathB = tenantPaymentDatabase();
    $this->tenantPaymentTestDatabases = [$pathA, $pathB];

    migrateTenantPaymentDatabase($pathA);
    migrateTenantPaymentDatabase($pathB);

    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    PaymentProviderAccount::query()->create([
        'tenant_id' => $tenantA->getKey(),
        'provider' => 'fake',
        'account_reference' => 'FAKE-A',
        'status' => 'active',
    ]);

    tenantPaymentContext($tenantA, $pathA);
    $fixtures = tenantPaymentFixtures();

    $calls = [];
    configureFakeTenantGateway();
    $payment = app(TenantPaymentManager::class)->createCheckout($fixtures['appointment']);

    DB::purge(TenantDatabaseManager::CONNECTION);
    app(TenantContext::class)->clear();

    tenantPaymentContext($tenantB, $pathB);

    expect(fn () => app(TenantPaymentManager::class)->markSucceeded($payment))
        ->toThrow(DomainException::class);
});
