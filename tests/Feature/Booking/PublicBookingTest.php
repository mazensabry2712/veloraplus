<?php

use App\Application\Booking\AppointmentManager;
use App\Application\Booking\PublicBookingManager;
use App\Application\Booking\ServiceManager;
use App\Application\Booking\StaffAvailabilityManager;
use App\Domain\Booking\AppointmentPaymentStatus;
use App\Infrastructure\Tenancy\TenantDatabaseManager;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\PaymentProviderAccount;
use App\Models\Service;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Domain\Tenancy\TenantContext;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
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

    if (isset($this->publicBookingDatabases)) {
        foreach ($this->publicBookingDatabases as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
});

function publicBookingDatabase(): string
{
    $directory = storage_path('framework/testing');

    File::ensureDirectoryExists($directory);

    $path = $directory.'/public-booking-'.Str::ulid().'.sqlite';

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

function migratePublicBookingDatabase(string $path): void
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

function publicBookingTenant(string $path): Tenant
{
    $tenant = Tenant::factory()->create([
        'name' => 'Public Clinic',
        'slug' => 'public-clinic',
        'database_name' => $path,
        'database_status' => 'ready',
        'status' => 'active',
    ]);

    TenantDomain::query()->create([
        'tenant_id' => $tenant->getKey(),
        'domain' => 'public-clinic.velora.test',
        'type' => 'subdomain',
        'is_primary' => true,
        'status' => 'active',
        'verified_at' => now(),
    ]);

    migratePublicBookingDatabase($path);

    return $tenant;
}

function publicBookingContext(Tenant $tenant, string $path): void
{
    $tenant->database_name = $path;

    app(TenantContext::class)->set($tenant);
    app(TenantDatabaseManager::class)->connect($tenant);
}

function publicBookingFixtures(bool $free = false, int $staffCount = 1): array
{
    $location = App\Models\Location::factory()->create([
        'name' => 'Main Branch',
        'timezone' => 'Africa/Cairo',
        'status' => 'active',
    ]);

    $service = Service::factory()->create([
        'name' => $free ? 'Free Consultation' : 'Paid Consultation',
        'slug' => $free ? 'free-consultation' : 'paid-consultation',
        'duration_minutes' => 60,
        'buffer_before_minutes' => 10,
        'buffer_after_minutes' => 10,
        'price_minor' => $free ? 0 : 15000,
        'currency' => 'EGP',
        'deposit_amount_minor' => 0,
        'status' => 'active',
        'online_bookable' => true,
        'capacity' => 1,
    ]);

    $staff = collect();

    for ($index = 1; $index <= $staffCount; $index++) {
        $member = Staff::factory()->forLocation($location)->create([
            'name' => 'Staff '.$index,
            'status' => 'active',
        ]);

        app(StaffAvailabilityManager::class)->assignService($member, $service);
        app(StaffAvailabilityManager::class)->saveWorkingHour($member, 1, '09:00', '17:00');

        $staff->push($member);
    }

    return [
        'location' => $location,
        'service' => $service,
        'staff' => $staff->all(),
    ];
}

test('public booking page renders only active staff and a non-indexable transactional form', function (): void {
    $path = publicBookingDatabase();
    $this->publicBookingDatabases = [$path];

    $tenant = publicBookingTenant($path);
    publicBookingContext($tenant, $path);

    $fixtures = publicBookingFixtures();

    app(Staff::class)->forceCreate;

    app(TenantDatabaseManager::class)->disconnect();
    app(TenantContext::class)->clear();

    $response = $this->get('https://public-clinic.velora.test/book/'.$fixtures['service']->slug);

    $response->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertSee('<h1>Book Paid Consultation</h1>', false)
        ->assertSee('name="customer_name"', false)
        ->assertSee('name="customer_phone"', false)
        ->assertSee('name="starts_at"', false)
        ->assertSee('name="idempotency_key"', false)
        ->assertSee('Staff 1', false);
});

test('free public booking creates a confirmed paid appointment without a payment record', function (): void {
    $path = publicBookingDatabase();
    $this->publicBookingDatabases = [$path];

    $tenant = publicBookingTenant($path);
    publicBookingContext($tenant, $path);

    $fixtures = publicBookingFixtures(free: true);

    app(TenantDatabaseManager::class)->disconnect();
    app(TenantContext::class)->clear();

    $response = $this->post(
        'https://public-clinic.velora.test/book/free-consultation',
        [
            'staff_id' => $fixtures['staff'][0]->getKey(),
            'starts_at' => '2026-09-21T10:00',
            'customer_name' => 'Public Customer',
            'customer_phone' => '+201000000000',
            'customer_email' => 'public@example.test',
            'idempotency_key' => 'public-free-1',
        ],
    );

    $response->assertOk()
        ->assertSee('Booking confirmed', false)
        ->assertSee('Public Customer', false);

    publicBookingContext($tenant, $path);

    $appointment = Appointment::query()->firstOrFail();

    expect($appointment->status->value)->toBe('confirmed')
        ->and($appointment->payment_status)->toBe(AppointmentPaymentStatus::Paid)
        ->and(Customer::query()->count())->toBe(1)
        ->and(Customer::query()->first()->source)->toBe('public_booking')
        ->and($appointment->payments()->count())->toBe(0);
});

test('paid public booking redirects to Tenant checkout and stores a pending payment', function (): void {
    $path = publicBookingDatabase();
    $this->publicBookingDatabases = [$path];

    $tenant = publicBookingTenant($path);

    PaymentProviderAccount::query()->create([
        'tenant_id' => $tenant->getKey(),
        'provider' => 'fake',
        'account_reference' => 'PUBLIC-FAKE',
        'status' => 'active',
        'encrypted_credentials' => ['account' => 'public'],
    ]);

    publicBookingContext($tenant, $path);
    $fixtures = publicBookingFixtures();

    app(TenantDatabaseManager::class)->disconnect();
    app(TenantContext::class)->clear();

    config([
        'velora.payments.tenant_provider' => 'fake',
        'velora.payments.drivers.fake' => FakeTenantPaymentGateway::class,
    ]);

    $response = $this->post(
        'https://public-clinic.velora.test/book/paid-consultation',
        [
            'staff_id' => $fixtures['staff'][0]->getKey(),
            'starts_at' => '2026-09-21T10:00',
            'customer_name' => 'Paid Customer',
            'customer_phone' => '+201100000000',
            'customer_email' => 'paid@example.test',
            'idempotency_key' => 'public-paid-1',
        ],
    );

    $response->assertRedirect('https://pay.example.test/session/1');

    publicBookingContext($tenant, $path);

    $appointment = Appointment::query()->firstOrFail();
    $payment = $appointment->payments()->firstOrFail();

    expect($payment->status->value)->toBe('pending')
        ->and($payment->amount_minor)->toBe(15000)
        ->and($payment->currency)->toBe('EGP')
        ->and($appointment->payment_status)->toBe(AppointmentPaymentStatus::Pending)
        ->and(FakeTenantPaymentGateway::$calls)->toHaveCount(1);
});

test('repeating the same public booking idempotency key does not create another customer appointment or checkout', function (): void {
    $path = publicBookingDatabase();
    $this->publicBookingDatabases = [$path];

    $tenant = publicBookingTenant($path);

    PaymentProviderAccount::query()->create([
        'tenant_id' => $tenant->getKey(),
        'provider' => 'fake',
        'account_reference' => 'PUBLIC-FAKE',
        'status' => 'active',
        'encrypted_credentials' => ['account' => 'public'],
    ]);

    publicBookingContext($tenant, $path);
    $fixtures = publicBookingFixtures();

    app(TenantDatabaseManager::class)->disconnect();
    app(TenantContext::class)->clear();

    config([
        'velora.payments.tenant_provider' => 'fake',
        'velora.payments.drivers.fake' => FakeTenantPaymentGateway::class,
    ]);

    $payload = [
        'staff_id' => $fixtures['staff'][0]->getKey(),
        'starts_at' => '2026-09-21T10:00',
        'customer_name' => 'Retry Customer',
        'customer_phone' => '+201200000000',
        'customer_email' => 'retry@example.test',
        'idempotency_key' => 'public-retry-1',
    ];

    $first = $this->post('https://public-clinic.velora.test/book/paid-consultation', $payload);
    $second = $this->post('https://public-clinic.velora.test/book/paid-consultation', $payload);

    $first->assertRedirect('https://pay.example.test/session/1');
    $second->assertRedirect('https://pay.example.test/session/1');

    publicBookingContext($tenant, $path);

    expect(Appointment::query()->count())->toBe(1)
        ->and(Customer::query()->count())->toBe(1)
        ->and(Appointment::query()->firstOrFail()->payments()->count())->toBe(1)
        ->and(FakeTenantPaymentGateway::$calls)->toHaveCount(1);
});

test('public paid booking is rejected before creating tenant data when merchant payment is unavailable', function (): void {
    $path = publicBookingDatabase();
    $this->publicBookingDatabases = [$path];

    $tenant = publicBookingTenant($path);
    publicBookingContext($tenant, $path);
    $fixtures = publicBookingFixtures();

    app(TenantDatabaseManager::class)->disconnect();
    app(TenantContext::class)->clear();

    config([
        'velora.payments.tenant_provider' => 'fake',
        'velora.payments.drivers.fake' => FakeTenantPaymentGateway::class,
    ]);

    $response = $this->from('https://public-clinic.velora.test/book/paid-consultation')
        ->post('https://public-clinic.velora.test/book/paid-consultation', [
            'staff_id' => $fixtures['staff'][0]->getKey(),
            'starts_at' => '2026-09-21T10:00',
            'customer_name' => 'No Payment',
            'customer_phone' => '+201300000000',
            'idempotency_key' => 'public-no-payment',
        ]);

    $response->assertRedirect()
        ->assertSessionHasErrors('booking');

    publicBookingContext($tenant, $path);

    expect(Appointment::query()->count())->toBe(0)
        ->and(Customer::query()->count())->toBe(0);
});

test('public booking requires staff selection when a service has multiple staff members', function (): void {
    $path = publicBookingDatabase();
    $this->publicBookingDatabases = [$path];

    $tenant = publicBookingTenant($path);
    publicBookingContext($tenant, $path);
    publicBookingFixtures(staffCount: 2);

    app(TenantDatabaseManager::class)->disconnect();
    app(TenantContext::class)->clear();

    $response = $this->from('https://public-clinic.velora.test/book/paid-consultation')
        ->post('https://public-clinic.velora.test/book/paid-consultation', [
            'starts_at' => '2026-09-21T10:00',
            'customer_name' => 'Needs Staff',
            'customer_phone' => '+201400000000',
            'idempotency_key' => 'public-no-staff',
        ]);

    $response->assertRedirect()
        ->assertSessionHasErrors('booking');
});

test('public booking uses the appointment final availability lock and rejects a conflicting slot', function (): void {
    $path = publicBookingDatabase();
    $this->publicBookingDatabases = [$path];

    $tenant = publicBookingTenant($path);
    publicBookingContext($tenant, $path);

    $fixtures = publicBookingFixtures();

    $customer = Customer::factory()->create([
        'name' => 'Existing Customer',
        'phone' => '+201500000000',
        'status' => 'active',
    ]);

    app(AppointmentManager::class)->create(
        customer: $customer,
        staff: $fixtures['staff'][0],
        service: $fixtures['service'],
        startsAt: '2026-09-21T10:00:00+03:00',
        attributes: ['idempotency_key' => 'existing-public-conflict'],
    );

    app(TenantDatabaseManager::class)->disconnect();
    app(TenantContext::class)->clear();

    $response = $this->from('https://public-clinic.velora.test/book/paid-consultation')
        ->post('https://public-clinic.velora.test/book/paid-consultation', [
            'staff_id' => $fixtures['staff'][0]->getKey(),
            'starts_at' => '2026-09-21T10:00',
            'customer_name' => 'Conflicting Customer',
            'customer_phone' => '+201600000000',
            'idempotency_key' => 'public-conflict',
        ]);

    $response->assertRedirect()
        ->assertSessionHasErrors('booking');

    publicBookingContext($tenant, $path);

    expect(Customer::query()->where('phone', '+201600000000')->exists())->toBeFalse();
});
