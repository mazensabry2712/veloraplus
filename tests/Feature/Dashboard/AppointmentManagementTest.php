<?php

use App\Application\Authorization\TenantRbacBootstrapper;
use App\Application\Booking\AppointmentManager;
use App\Application\Booking\ServiceManager;
use App\Application\Booking\StaffAvailabilityManager;
use App\Application\Entitlements\EntitlementService;
use App\Infrastructure\Tenancy\TenantDatabaseManager;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Feature;
use App\Models\Location;
use App\Models\Module;
use App\Models\PlatformAccount;
use App\Models\Service;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TenantMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->originalTenantTemplate = config('database.connections.tenant_template');
});

function appointmentDashboardDatabasePath(): string
{
    $directory = storage_path('framework/testing');

    File::ensureDirectoryExists($directory);

    $path = $directory.'/dashboard-appointments-'.Str::ulid().'.sqlite';
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

function createAppointmentDashboardTenant(
    string $path,
    string $domain = 'appointments-tenant.velora.test',
    string $slug = 'appointments-tenant',
): Tenant {
    $tenant = Tenant::factory()->create([
        'name' => 'Appointments Tenant',
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

function addAppointmentDashboardMember(Tenant $tenant, string $roleKey = 'owner'): PlatformAccount
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

function enableAppointmentEntitlement(Tenant $tenant, ?Feature $feature = null): Feature
{
    if ($feature === null) {
        $module = Module::factory()->create([
            'key' => 'booking',
            'name' => 'Booking',
            'status' => 'active',
        ]);

        $feature = Feature::factory()->create([
            'module_id' => $module->getKey(),
            'key' => 'booking.appointments',
            'name' => 'Appointments',
            'status' => 'active',
        ]);
    }

    app(EntitlementService::class)->grant($tenant, $feature);

    return $feature;
}

function appointmentDashboardFixtures(Tenant $tenant): array
{
    $manager = app(TenantDatabaseManager::class);
    $manager->connect($tenant);

    try {
        $location = Location::query()->create([
            'name' => 'Main Branch',
            'code' => 'APPT',
            'country_code' => 'EG',
            'city' => 'Cairo',
            'timezone' => 'Africa/Cairo',
            'status' => 'active',
            'metadata' => [],
        ]);

        $staff = Staff::query()->create([
            'location_id' => $location->getKey(),
            'name' => 'Dr. Ahmed',
            'email' => 'doctor@example.test',
            'status' => 'active',
            'metadata' => [],
        ]);

        $customer = Customer::query()->create([
            'name' => 'Customer One',
            'phone' => '+201000000000',
            'email' => 'customer@example.test',
            'status' => 'active',
            'source' => 'dashboard',
            'metadata' => [],
        ]);

        $service = app(ServiceManager::class)->create([
            'name' => 'Consultation',
            'duration_minutes' => 60,
            'buffer_before_minutes' => 10,
            'buffer_after_minutes' => 10,
            'price_minor' => 50000,
            'currency' => 'EGP',
            'deposit_amount_minor' => 0,
            'capacity' => 1,
        ]);

        $availability = app(StaffAvailabilityManager::class);
        $availability->assignService($staff, $service);
        $availability->saveWorkingHour($staff, 1, '09:00', '17:00');

        return compact('location', 'staff', 'customer', 'service');
    } finally {
        $manager->disconnect();
    }
}

afterEach(function (): void {
    DB::purge(TenantDatabaseManager::CONNECTION);
    DB::setDefaultConnection('central');
    app(\App\Domain\Tenancy\TenantContext::class)->clear();
    setPermissionsTeamId(null);

    config([
        'database.connections.tenant_template' => $this->originalTenantTemplate,
    ]);

    if (isset($this->appointmentDashboardDatabasePath) && is_file($this->appointmentDashboardDatabasePath)) {
        unlink($this->appointmentDashboardDatabasePath);
    }
});

test('owner can create reschedule and complete an appointment through the dashboard backend', function (): void {
    $path = appointmentDashboardDatabasePath();
    $this->appointmentDashboardDatabasePath = $path;

    $tenant = createAppointmentDashboardTenant($path);
    $owner = addAppointmentDashboardMember($tenant);
    enableAppointmentEntitlement($tenant);
    $fixtures = appointmentDashboardFixtures($tenant);

    $this->actingAs($owner)
        ->post('http://appointments-tenant.velora.test/dashboard/booking/appointments', [
            'customer_id' => $fixtures['customer']->getKey(),
            'staff_id' => $fixtures['staff']->getKey(),
            'service_id' => $fixtures['service']->getKey(),
            'starts_at' => '2026-09-21 10:00:00+03:00',
            'idempotency_key' => 'dashboard-appt-1',
            'notes' => 'Admin booking',
        ])
        ->assertRedirect('/dashboard')
        ->assertSessionHas('status', 'Appointment created successfully.');

    $manager = app(TenantDatabaseManager::class);
    $manager->connect($tenant);

    try {
        $appointment = Appointment::query()->firstOrFail();

        $this->actingAs($owner)
            ->patch('http://appointments-tenant.velora.test/dashboard/booking/appointments/'.$appointment->getKey(), [
                'starts_at' => '2026-09-21 14:00:00+03:00',
            ])
            ->assertRedirect('/dashboard')
            ->assertSessionHas('status', 'Appointment rescheduled successfully.');

        $this->actingAs($owner)
            ->post('http://appointments-tenant.velora.test/dashboard/booking/appointments/'.$appointment->getKey().'/complete')
            ->assertRedirect('/dashboard')
            ->assertSessionHas('status', 'Appointment completed successfully.');

        expect($appointment->refresh()->status->value)->toBe('completed');
    } finally {
        $manager->disconnect();
    }
});

test('viewer cannot mutate appointments', function (): void {
    $path = appointmentDashboardDatabasePath();
    $this->appointmentDashboardDatabasePath = $path;

    $tenant = createAppointmentDashboardTenant($path);
    $viewer = addAppointmentDashboardMember($tenant, 'viewer');
    enableAppointmentEntitlement($tenant);
    $fixtures = appointmentDashboardFixtures($tenant);

    $this->actingAs($viewer)
        ->post('http://appointments-tenant.velora.test/dashboard/booking/appointments', [
            'customer_id' => $fixtures['customer']->getKey(),
            'staff_id' => $fixtures['staff']->getKey(),
            'service_id' => $fixtures['service']->getKey(),
            'starts_at' => '2026-09-21 10:00:00+03:00',
        ])
        ->assertForbidden();
});

test('appointment entitlement is required for dashboard mutations', function (): void {
    $path = appointmentDashboardDatabasePath();
    $this->appointmentDashboardDatabasePath = $path;

    $tenant = createAppointmentDashboardTenant($path);
    $owner = addAppointmentDashboardMember($tenant);
    $fixtures = appointmentDashboardFixtures($tenant);

    $this->actingAs($owner)
        ->post('http://appointments-tenant.velora.test/dashboard/booking/appointments', [
            'customer_id' => $fixtures['customer']->getKey(),
            'staff_id' => $fixtures['staff']->getKey(),
            'service_id' => $fixtures['service']->getKey(),
            'starts_at' => '2026-09-21 10:00:00+03:00',
        ])
        ->assertForbidden();
});

test('appointment input validation prevents malformed or missing records', function (): void {
    $path = appointmentDashboardDatabasePath();
    $this->appointmentDashboardDatabasePath = $path;

    $tenant = createAppointmentDashboardTenant($path);
    $owner = addAppointmentDashboardMember($tenant);
    enableAppointmentEntitlement($tenant);

    $this->actingAs($owner)
        ->post('http://appointments-tenant.velora.test/dashboard/booking/appointments', [
            'customer_id' => 'missing',
            'staff_id' => 'missing',
            'service_id' => 'missing',
            'starts_at' => 'not-a-date',
        ])
        ->assertSessionHasErrors([
            'customer_id',
            'staff_id',
            'service_id',
            'starts_at',
        ]);
});

test('appointment lifecycle operations reuse domain transition rules', function (): void {
    $path = appointmentDashboardDatabasePath();
    $this->appointmentDashboardDatabasePath = $path;

    $tenant = createAppointmentDashboardTenant($path);
    $owner = addAppointmentDashboardMember($tenant);
    enableAppointmentEntitlement($tenant);
    $fixtures = appointmentDashboardFixtures($tenant);

    $manager = app(TenantDatabaseManager::class);
    $manager->connect($tenant);

    try {
        $appointment = app(AppointmentManager::class)->create(
            customer: $fixtures['customer'],
            staff: $fixtures['staff'],
            service: $fixtures['service'],
            startsAt: '2026-09-21 10:00:00+03:00',
            attributes: ['idempotency_key' => 'lifecycle-dashboard'],
        );
    } finally {
        $manager->disconnect();
    }

    $this->actingAs($owner)
        ->post('http://appointments-tenant.velora.test/dashboard/booking/appointments/'.$appointment->getKey().'/cancel', [
            'reason' => 'Customer requested',
        ])
        ->assertRedirect('/dashboard');

    $this->actingAs($owner)
        ->post('http://appointments-tenant.velora.test/dashboard/booking/appointments/'.$appointment->getKey().'/complete')
        ->assertSessionHasErrors('appointment');
});

test('appointment route binding is tenant isolated', function (): void {
    $pathA = appointmentDashboardDatabasePath();
    $pathB = appointmentDashboardDatabasePath();
    $this->appointmentDashboardDatabasePath = $pathA;

    $tenantA = createAppointmentDashboardTenant($pathA, 'appointments-a.velora.test', 'appointments-a');
    $tenantB = createAppointmentDashboardTenant($pathB, 'appointments-b.velora.test', 'appointments-b');
    $ownerA = addAppointmentDashboardMember($tenantA);
    $appointmentFeature = enableAppointmentEntitlement($tenantA);
    enableAppointmentEntitlement($tenantB, $appointmentFeature);

    $manager = app(TenantDatabaseManager::class);
    $manager->connect($tenantB);
    try {
        $fixturesB = appointmentDashboardFixtures($tenantB);
    } finally {
        $manager->disconnect();
        DB::purge(TenantDatabaseManager::CONNECTION);
    }

    $manager->connect($tenantB);
    try {
        $appointmentB = app(AppointmentManager::class)->create(
            customer: $fixturesB['customer'],
            staff: $fixturesB['staff'],
            service: $fixturesB['service'],
            startsAt: '2026-09-21 10:00:00+03:00',
            attributes: ['idempotency_key' => 'tenant-b-dashboard'],
        );
    } finally {
        $manager->disconnect();
        DB::purge(TenantDatabaseManager::CONNECTION);
    }

    $this->actingAs($ownerA)
        ->patch('http://appointments-a.velora.test/dashboard/booking/appointments/'.$appointmentB->getKey(), [
            'starts_at' => '2026-09-21 12:00:00+03:00',
        ])
        ->assertNotFound();

    if (is_file($pathB)) {
        unlink($pathB);
    }
});

test('owner can open the Booking Appointments dashboard and see lifecycle actions', function (): void {
    $path = appointmentDashboardDatabasePath();
    $this->appointmentDashboardDatabasePath = $path;

    $tenant = createAppointmentDashboardTenant($path);
    $owner = addAppointmentDashboardMember($tenant);
    enableAppointmentEntitlement($tenant);
    $fixtures = appointmentDashboardFixtures($tenant);

    $manager = app(TenantDatabaseManager::class);
    $manager->connect($tenant);

    try {
        app(AppointmentManager::class)->create(
            customer: $fixtures['customer'],
            staff: $fixtures['staff'],
            service: $fixtures['service'],
            startsAt: '2026-09-21 10:00:00+03:00',
            attributes: ['idempotency_key' => 'dashboard-list-1'],
        );
    } finally {
        $manager->disconnect();
    }

    $this->actingAs($owner)
        ->get('http://appointments-tenant.velora.test/dashboard/booking/appointments')
        ->assertOk()
        ->assertSee('Appointments', false)
        ->assertSee('Customer One', false)
        ->assertSee('Dr. Ahmed', false)
        ->assertSee('Consultation', false)
        ->assertSee('Confirmed', false)
        ->assertSee('Unpaid', false)
        ->assertSee('Create Appointment', false)
        ->assertSee('Reschedule', false)
        ->assertSee('Complete', false)
        ->assertSee('No-show', false)
        ->assertSee('Cancel', false);
});

test('viewer can read Booking Appointments but cannot see management controls', function (): void {
    $path = appointmentDashboardDatabasePath();
    $this->appointmentDashboardDatabasePath = $path;

    $tenant = createAppointmentDashboardTenant($path);
    $viewer = addAppointmentDashboardMember($tenant, 'viewer');
    enableAppointmentEntitlement($tenant);
    $fixtures = appointmentDashboardFixtures($tenant);

    $manager = app(TenantDatabaseManager::class);
    $manager->connect($tenant);

    try {
        $appointment = app(AppointmentManager::class)->create(
            customer: $fixtures['customer'],
            staff: $fixtures['staff'],
            service: $fixtures['service'],
            startsAt: '2026-09-21 10:00:00+03:00',
            attributes: ['idempotency_key' => 'dashboard-list-viewer'],
        );
    } finally {
        $manager->disconnect();
    }

    $this->actingAs($viewer)
        ->get('http://appointments-tenant.velora.test/dashboard/booking/appointments')
        ->assertOk()
        ->assertSee('Customer One', false)
        ->assertSee('Dr. Ahmed', false)
        ->assertDontSee('Create Appointment', false)
        ->assertDontSee('Reschedule', false)
        ->assertDontSee('No-show', false)
        ->assertDontSee('Cancel Appointment', false)
        ->assertDontSee('/dashboard/booking/appointments/'.$appointment->getKey().'/complete', false)
        ->assertDontSee('/dashboard/booking/appointments/'.$appointment->getKey().'/reschedule', false)
        ->assertDontSee('/dashboard/booking/appointments/'.$appointment->getKey().'/no-show', false)
        ->assertDontSee('/dashboard/booking/appointments/'.$appointment->getKey().'/cancel', false);
});

test('Booking Appointments dashboard requires the feature entitlement', function (): void {
    $path = appointmentDashboardDatabasePath();
    $this->appointmentDashboardDatabasePath = $path;

    $tenant = createAppointmentDashboardTenant($path);
    $owner = addAppointmentDashboardMember($tenant);

    $this->actingAs($owner)
        ->get('http://appointments-tenant.velora.test/dashboard/booking/appointments')
        ->assertForbidden();
});

