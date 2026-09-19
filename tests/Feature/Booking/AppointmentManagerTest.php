<?php

use App\Application\Booking\AppointmentManager;
use App\Application\Booking\ServiceManager;
use App\Application\Booking\StaffAvailabilityManager;
use App\Domain\Booking\AppointmentPaymentStatus;
use App\Domain\Booking\AppointmentStatus;
use App\Infrastructure\Tenancy\TenantDatabaseManager;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Service;
use App\Models\Staff;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

afterEach(function () {
    DB::purge(TenantDatabaseManager::CONNECTION);
    DB::setDefaultConnection('central');
    setPermissionsTeamId(null);

    if (isset($this->appointmentTestDatabases)) {
        foreach ($this->appointmentTestDatabases as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
});

function appointmentTestDatabase(): string
{
    $directory = storage_path('framework/testing');

    File::ensureDirectoryExists($directory);

    $path = $directory.'/appointment-'.Str::ulid().'.sqlite';

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

function migrateAppointmentTestDatabase(string $path): void
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

function appointmentFixtures(): array
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
        'status' => 'active',
    ]);

    app(StaffAvailabilityManager::class)->assignService($staff, $service);
    app(StaffAvailabilityManager::class)->saveWorkingHour($staff, 1, '09:00', '17:00');

    return compact('location', 'staff', 'customer', 'service');
}

test('appointment migrations create scheduling and history schema', function () {
    $path = appointmentTestDatabase();
    $this->appointmentTestDatabases = [$path];

    migrateAppointmentTestDatabase($path);

    $tenant = new Tenant;
    $tenant->database_name = $path;

    $manager = app(TenantDatabaseManager::class);
    $manager->connect($tenant);

    expect(Schema::connection(TenantDatabaseManager::CONNECTION)->hasTable('appointments'))->toBeTrue()
        ->and(Schema::connection(TenantDatabaseManager::CONNECTION)->hasTable('appointment_items'))->toBeTrue()
        ->and(Schema::connection(TenantDatabaseManager::CONNECTION)->hasTable('appointment_status_histories'))->toBeTrue()
        ->and((new Appointment)->getConnectionName())->toBe(TenantDatabaseManager::CONNECTION);

    $manager->disconnect();
});

test('appointment manager creates an available appointment with snapshots and history', function () {
    $path = appointmentTestDatabase();
    $this->appointmentTestDatabases = [$path];

    migrateAppointmentTestDatabase($path);

    $tenant = new Tenant;
    $tenant->database_name = $path;

    $manager = app(TenantDatabaseManager::class);
    $manager->connect($tenant);

    extract(appointmentFixtures());

    $startsAt = '2026-09-21 10:00:00+03:00';

    $appointment = app(AppointmentManager::class)->create(
        customer: $customer,
        staff: $staff,
        service: $service,
        startsAt: $startsAt,
        attributes: [
            'idempotency_key' => 'appt-1001',
            'notes' => 'First booking',
            'metadata' => ['source' => 'admin'],
        ],
    );

    expect($appointment->exists)->toBeTrue()
        ->and($appointment->status)->toBe(AppointmentStatus::Confirmed)
        ->and($appointment->payment_status)->toBe(AppointmentPaymentStatus::Unpaid)
        ->and($appointment->customer_id)->toBe($customer->getKey())
        ->and($appointment->staff_id)->toBe($staff->getKey())
        ->and($appointment->location_id)->toBe($location->getKey())
        ->and($appointment->starts_at->timezone('Africa/Cairo')->format('Y-m-d H:i'))->toBe('2026-09-21 10:00')
        ->and($appointment->ends_at->timezone('Africa/Cairo')->format('Y-m-d H:i'))->toBe('2026-09-21 11:00')
        ->and($appointment->blocked_starts_at->timezone('Africa/Cairo')->format('Y-m-d H:i'))->toBe('2026-09-21 09:50')
        ->and($appointment->blocked_ends_at->timezone('Africa/Cairo')->format('Y-m-d H:i'))->toBe('2026-09-21 11:10')
        ->and($appointment->items)->toHaveCount(1)
        ->and($appointment->items->first()->service_name)->toBe($service->name)
        ->and($appointment->items->first()->unit_price_minor)->toBe($service->price_minor)
        ->and($appointment->items->first()->currency)->toBe($service->currency)
        ->and($appointment->statusHistories)->toHaveCount(1)
        ->and($appointment->statusHistories->first()->from_status)->toBeNull()
        ->and($appointment->statusHistories->first()->to_status)->toBe(AppointmentStatus::Confirmed->value);

    $manager->disconnect();
});

test('appointment manager rejects unavailable times, unassigned services, and mismatched locations', function () {
    $path = appointmentTestDatabase();
    $this->appointmentTestDatabases = [$path];

    migrateAppointmentTestDatabase($path);

    $tenant = new Tenant;
    $tenant->database_name = $path;

    $manager = app(TenantDatabaseManager::class);
    $manager->connect($tenant);

    extract(appointmentFixtures());

    $appointments = app(AppointmentManager::class);

    expect(fn () => $appointments->create(
        $customer,
        $staff,
        $service,
        '2026-09-21 08:00:00+03:00',
    ))->toThrow(DomainException::class);

    $otherService = Service::factory()->create();

    expect(fn () => $appointments->create(
        $customer,
        $staff,
        $otherService,
        '2026-09-21 10:00:00+03:00',
    ))->toThrow(DomainException::class);

    $otherLocation = Location::factory()->create([
        'timezone' => 'Africa/Cairo',
    ]);

    expect(fn () => $appointments->create(
        $customer,
        $staff,
        $service,
        '2026-09-21 10:00:00+03:00',
        ['location_id' => $otherLocation->getKey()],
    ))->toThrow(DomainException::class);

    $manager->disconnect();
});

test('appointment conflict checks include service buffers and ignore cancelled appointments', function () {
    $path = appointmentTestDatabase();
    $this->appointmentTestDatabases = [$path];

    migrateAppointmentTestDatabase($path);

    $tenant = new Tenant;
    $tenant->database_name = $path;

    $manager = app(TenantDatabaseManager::class);
    $manager->connect($tenant);

    extract(appointmentFixtures());

    $appointments = app(AppointmentManager::class);

    $first = $appointments->create(
        $customer,
        $staff,
        $service,
        '2026-09-21 10:00:00+03:00',
        ['idempotency_key' => 'appt-conflict-1'],
    );

    expect(fn () => $appointments->create(
        $customer,
        $staff,
        $service,
        '2026-09-21 11:00:00+03:00',
    ))->toThrow(DomainException::class);

    $cancelled = $appointments->create(
        $customer,
        $staff,
        $service,
        '2026-09-21 13:00:00+03:00',
        ['idempotency_key' => 'appt-cancelled'],
    );

    $appointments->cancel($cancelled, 'Customer requested cancellation');

    $replacement = $appointments->create(
        $customer,
        $staff,
        $service,
        '2026-09-21 13:00:00+03:00',
        ['idempotency_key' => 'appt-replacement'],
    );

    expect($first->exists)->toBeTrue()
        ->and($replacement->exists)->toBeTrue()
        ->and($replacement->status)->toBe(AppointmentStatus::Confirmed)
        ->and(Appointment::query()->where('status', AppointmentStatus::Cancelled->value)->count())->toBe(1);

    $manager->disconnect();
});

test('appointment idempotency returns the existing appointment and rejects key reuse with different data', function () {
    $path = appointmentTestDatabase();
    $this->appointmentTestDatabases = [$path];

    migrateAppointmentTestDatabase($path);

    $tenant = new Tenant;
    $tenant->database_name = $path;

    $manager = app(TenantDatabaseManager::class);
    $manager->connect($tenant);

    extract(appointmentFixtures());

    $appointments = app(AppointmentManager::class);

    $first = $appointments->create(
        $customer,
        $staff,
        $service,
        '2026-09-21 10:00:00+03:00',
        ['idempotency_key' => 'same-request'],
    );

    $same = $appointments->create(
        $customer,
        $staff,
        $service,
        '2026-09-21 10:00:00+03:00',
        ['idempotency_key' => 'same-request', 'notes' => 'retry'],
    );

    expect($same->getKey())->toBe($first->getKey())
        ->and(Appointment::query()->count())->toBe(1);

    expect(fn () => $appointments->create(
        $customer,
        $staff,
        $service,
        '2026-09-21 12:00:00+03:00',
        ['idempotency_key' => 'same-request'],
    ))->toThrow(DomainException::class);

    $manager->disconnect();
});

test('appointment reschedule and lifecycle transitions are transactional and historical', function () {
    $path = appointmentTestDatabase();
    $this->appointmentTestDatabases = [$path];

    migrateAppointmentTestDatabase($path);

    $tenant = new Tenant;
    $tenant->database_name = $path;

    $manager = app(TenantDatabaseManager::class);
    $manager->connect($tenant);

    extract(appointmentFixtures());

    $appointments = app(AppointmentManager::class);

    $appointment = $appointments->create(
        $customer,
        $staff,
        $service,
        '2026-09-21 10:00:00+03:00',
        ['idempotency_key' => 'lifecycle-1'],
    );

    $rescheduled = $appointments->reschedule(
        $appointment,
        '2026-09-21 14:00:00+03:00',
    );

    expect($rescheduled->starts_at->timezone('Africa/Cairo')->format('H:i'))->toBe('14:00')
        ->and($rescheduled->ends_at->timezone('Africa/Cairo')->format('H:i'))->toBe('15:00')
        ->and($rescheduled->blocked_starts_at->timezone('Africa/Cairo')->format('H:i'))->toBe('13:50')
        ->and($rescheduled->blocked_ends_at->timezone('Africa/Cairo')->format('H:i'))->toBe('15:10');

    $completed = $appointments->complete($rescheduled);

    expect($completed->status)->toBe(AppointmentStatus::Completed)
        ->and($completed->statusHistories)->toHaveCount(2);

    expect(fn () => $appointments->reschedule($completed, '2026-09-21 15:00:00+03:00'))
        ->toThrow(DomainException::class)
        ->and(fn () => $appointments->cancel($completed, 'Too late'))
        ->toThrow(DomainException::class);

    $manager->disconnect();
});

test('appointment cancel records reason and releases the scheduling slot', function () {
    $path = appointmentTestDatabase();
    $this->appointmentTestDatabases = [$path];

    migrateAppointmentTestDatabase($path);

    $tenant = new Tenant;
    $tenant->database_name = $path;

    $manager = app(TenantDatabaseManager::class);
    $manager->connect($tenant);

    extract(appointmentFixtures());

    $appointments = app(AppointmentManager::class);

    $appointment = $appointments->create(
        $customer,
        $staff,
        $service,
        '2026-09-21 10:00:00+03:00',
        ['idempotency_key' => 'cancel-1'],
    );

    $cancelled = $appointments->cancel($appointment, 'Customer cancelled');

    expect($cancelled->status)->toBe(AppointmentStatus::Cancelled)
        ->and($cancelled->cancellation_reason)->toBe('Customer cancelled')
        ->and($cancelled->cancelled_at)->not->toBeNull()
        ->and($cancelled->statusHistories)->toHaveCount(2);

    $replacement = $appointments->create(
        $customer,
        $staff,
        $service,
        '2026-09-21 10:00:00+03:00',
        ['idempotency_key' => 'cancel-replacement'],
    );

    expect($replacement->exists)->toBeTrue();

    $manager->disconnect();
});

test('appointment records remain isolated between tenant databases', function () {
    $pathA = appointmentTestDatabase();
    $pathB = appointmentTestDatabase();
    $this->appointmentTestDatabases = [$pathA, $pathB];

    migrateAppointmentTestDatabase($pathA);
    migrateAppointmentTestDatabase($pathB);

    $manager = app(TenantDatabaseManager::class);

    $tenantA = new Tenant;
    $tenantA->database_name = $pathA;

    $manager->connect($tenantA);

    $fixturesA = appointmentFixtures();
    $appointmentA = app(AppointmentManager::class)->create(
        $fixturesA['customer'],
        $fixturesA['staff'],
        $fixturesA['service'],
        '2026-09-21 10:00:00+03:00',
        ['idempotency_key' => 'tenant-a-appointment'],
    );

    $manager->disconnect();

    $tenantB = new Tenant;
    $tenantB->database_name = $pathB;

    $manager->connect($tenantB);

    expect(Appointment::query()->whereKey($appointmentA->getKey())->exists())->toBeFalse();

    $manager->disconnect();
});
