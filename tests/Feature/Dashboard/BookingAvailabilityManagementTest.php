<?php

use App\Application\Authorization\TenantRbacBootstrapper;
use App\Application\Booking\StaffAvailabilityManager;
use App\Application\Entitlements\EntitlementService;
use App\Infrastructure\Tenancy\TenantDatabaseManager;
use App\Models\Feature;
use App\Models\Location;
use App\Models\Module;
use App\Models\PlatformAccount;
use App\Models\Service;
use App\Models\Staff;
use App\Models\StaffBreak;
use App\Models\StaffTimeOff;
use App\Models\StaffWorkingHour;
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

function bookingAvailabilityDashboardDatabasePath(): string
{
    $directory = storage_path('framework/testing');

    File::ensureDirectoryExists($directory);

    $path = $directory.'/dashboard-availability-'.Str::ulid().'.sqlite';
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

function createBookingAvailabilityDashboardTenant(
    string $path,
    string $domain = 'availability-tenant.velora.test',
    string $slug = 'availability-tenant',
): Tenant {
    $tenant = Tenant::factory()->create([
        'name' => 'Availability Tenant',
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

function addBookingAvailabilityMember(Tenant $tenant, string $roleKey = 'owner'): PlatformAccount
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

function enableBookingAvailabilityFeature(Tenant $tenant, string $key): void
{
    $module = Module::factory()->create([
        'key' => 'booking',
        'name' => 'Booking',
        'status' => 'active',
    ]);

    $feature = Feature::factory()->create([
        'module_id' => $module->getKey(),
        'key' => $key,
        'name' => $key,
        'status' => 'active',
    ]);

    app(EntitlementService::class)->grant($tenant, $feature);
}

function availabilityLocation(Tenant $tenant): Location
{
    $manager = app(TenantDatabaseManager::class);
    $manager->connect($tenant);

    try {
        return Location::query()->create([
            'name' => 'Main Branch',
            'code' => 'MAIN',
            'country_code' => 'EG',
            'city' => 'Cairo',
            'timezone' => 'Africa/Cairo',
            'status' => 'active',
            'metadata' => [],
        ]);
    } finally {
        $manager->disconnect();
    }
}

function availabilityStaff(Tenant $tenant, Location $location): Staff
{
    $manager = app(TenantDatabaseManager::class);
    $manager->connect($tenant);

    try {
        return Staff::query()->create([
            'location_id' => $location->getKey(),
            'name' => 'Dr. Ahmed',
            'email' => 'ahmed@example.test',
            'status' => 'active',
            'metadata' => [],
        ]);
    } finally {
        $manager->disconnect();
    }
}

function availabilityService(Tenant $tenant): Service
{
    $manager = app(TenantDatabaseManager::class);
    $manager->connect($tenant);

    try {
        return Service::factory()->create([
            'name' => 'Consultation',
            'duration_minutes' => 60,
            'price_minor' => 50000,
            'currency' => 'EGP',
            'deposit_amount_minor' => 0,
            'capacity' => 1,
        ]);
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

    if (isset($this->bookingAvailabilityDashboardDatabasePath) && is_file($this->bookingAvailabilityDashboardDatabasePath)) {
        unlink($this->bookingAvailabilityDashboardDatabasePath);
    }
});

test('owner can manage working hours breaks and time off through the dashboard backend', function (): void {
    $path = bookingAvailabilityDashboardDatabasePath();
    $this->bookingAvailabilityDashboardDatabasePath = $path;

    $tenant = createBookingAvailabilityDashboardTenant($path);
    $owner = addBookingAvailabilityMember($tenant);
    enableBookingAvailabilityFeature($tenant, 'booking.availability');

    $location = availabilityLocation($tenant);
    $staff = availabilityStaff($tenant, $location);

    $this->actingAs($owner)
        ->post('http://availability-tenant.velora.test/dashboard/booking/availability/staff/'.$staff->getKey().'/working-hours', [
            'day_of_week' => 1,
            'starts_at' => '09:00',
            'ends_at' => '17:00',
        ])
        ->assertRedirect('/dashboard')
        ->assertSessionHas('status', 'Staff working hour saved successfully.');

    $manager = app(TenantDatabaseManager::class);
    $manager->connect($tenant);

    try {
        $workingHour = StaffWorkingHour::query()->firstOrFail();

        $this->actingAs($owner)
            ->post('http://availability-tenant.velora.test/dashboard/booking/availability/staff/'.$staff->getKey().'/working-hours/'.$workingHour->getKey().'/breaks', [
                'starts_at' => '12:00',
                'ends_at' => '13:00',
                'label' => 'Lunch',
            ])
            ->assertRedirect('/dashboard')
            ->assertSessionHas('status', 'Staff break saved successfully.');

        $break = StaffBreak::query()->firstOrFail();

        $this->actingAs($owner)
            ->patch('http://availability-tenant.velora.test/dashboard/booking/availability/staff/'.$staff->getKey().'/working-hours/'.$workingHour->getKey(), [
                'day_of_week' => 1,
                'starts_at' => '10:00',
                'ends_at' => '18:00',
            ])
            ->assertRedirect('/dashboard');

        $updatedHour = $workingHour->refresh();
        expect($updatedHour->starts_at)->toBe('10:00:00')
            ->and($updatedHour->ends_at)->toBe('18:00:00');

        $this->actingAs($owner)
            ->post('http://availability-tenant.velora.test/dashboard/booking/availability/staff/'.$staff->getKey().'/time-off', [
                'starts_at' => '2026-09-21T12:00+03:00',
                'ends_at' => '2026-09-21T15:00+03:00',
                'reason' => 'Holiday',
            ])
            ->assertRedirect('/dashboard')
            ->assertSessionHas('status', 'Staff time off saved successfully.');

        $timeOff = StaffTimeOff::query()->firstOrFail();

        $this->actingAs($owner)
            ->patch('http://availability-tenant.velora.test/dashboard/booking/availability/staff/'.$staff->getKey().'/time-off/'.$timeOff->getKey(), [
                'starts_at' => '2026-09-21T13:00+03:00',
                'ends_at' => '2026-09-21T14:00+03:00',
                'reason' => 'Updated',
            ])
            ->assertRedirect('/dashboard');

        expect($timeOff->refresh()->reason)->toBe('Updated');

        $this->actingAs($owner)
            ->delete('http://availability-tenant.velora.test/dashboard/booking/availability/staff/'.$staff->getKey().'/working-hours/'.$workingHour->getKey().'/breaks/'.$break->getKey())
            ->assertRedirect('/dashboard');

        $this->actingAs($owner)
            ->delete('http://availability-tenant.velora.test/dashboard/booking/availability/staff/'.$staff->getKey().'/working-hours/'.$workingHour->getKey())
            ->assertRedirect('/dashboard');

        expect(StaffBreak::query()->find($break->getKey()))->toBeNull()
            ->and(StaffWorkingHour::query()->find($workingHour->getKey()))->toBeNull();

        $this->actingAs($owner)
            ->delete('http://availability-tenant.velora.test/dashboard/booking/availability/staff/'.$staff->getKey().'/time-off/'.$timeOff->getKey())
            ->assertRedirect('/dashboard');

        expect(StaffTimeOff::query()->find($timeOff->getKey()))->toBeNull();
    } finally {
        $manager->disconnect();
    }
});

test('owner can assign a booking service to staff when both entitlements exist', function (): void {
    $path = bookingAvailabilityDashboardDatabasePath();
    $this->bookingAvailabilityDashboardDatabasePath = $path;

    $tenant = createBookingAvailabilityDashboardTenant($path);
    $owner = addBookingAvailabilityMember($tenant);
    enableBookingAvailabilityFeature($tenant, 'booking.availability');
    enableBookingAvailabilityFeature($tenant, 'booking.services');

    $location = availabilityLocation($tenant);
    $staff = availabilityStaff($tenant, $location);
    $service = availabilityService($tenant);

    $this->actingAs($owner)
        ->post('http://availability-tenant.velora.test/dashboard/booking/availability/staff/'.$staff->getKey().'/services/'.$service->getKey())
        ->assertRedirect('/dashboard')
        ->assertSessionHas('status', 'Service assigned to staff successfully.');

    $manager = app(TenantDatabaseManager::class);
    $manager->connect($tenant);

    try {
        expect($staff->fresh()->services()->whereKey($service->getKey())->exists())->toBeTrue();
    } finally {
        $manager->disconnect();
    }
});

test('viewer cannot manage staff availability', function (): void {
    $path = bookingAvailabilityDashboardDatabasePath();
    $this->bookingAvailabilityDashboardDatabasePath = $path;

    $tenant = createBookingAvailabilityDashboardTenant($path);
    $viewer = addBookingAvailabilityMember($tenant, 'viewer');
    enableBookingAvailabilityFeature($tenant, 'booking.availability');

    $location = availabilityLocation($tenant);
    $staff = availabilityStaff($tenant, $location);

    $this->actingAs($viewer)
        ->post('http://availability-tenant.velora.test/dashboard/booking/availability/staff/'.$staff->getKey().'/working-hours', [
            'day_of_week' => 1,
            'starts_at' => '09:00',
            'ends_at' => '17:00',
        ])
        ->assertForbidden();
});

test('availability entitlement is required before backend mutation', function (): void {
    $path = bookingAvailabilityDashboardDatabasePath();
    $this->bookingAvailabilityDashboardDatabasePath = $path;

    $tenant = createBookingAvailabilityDashboardTenant($path);
    $owner = addBookingAvailabilityMember($tenant);
    $location = availabilityLocation($tenant);
    $staff = availabilityStaff($tenant, $location);

    $this->actingAs($owner)
        ->post('http://availability-tenant.velora.test/dashboard/booking/availability/staff/'.$staff->getKey().'/working-hours', [
            'day_of_week' => 1,
            'starts_at' => '09:00',
            'ends_at' => '17:00',
        ])
        ->assertForbidden();
});

test('availability validation and ownership checks protect nested records', function (): void {
    $path = bookingAvailabilityDashboardDatabasePath();
    $this->bookingAvailabilityDashboardDatabasePath = $path;

    $tenant = createBookingAvailabilityDashboardTenant($path);
    $owner = addBookingAvailabilityMember($tenant);
    enableBookingAvailabilityFeature($tenant, 'booking.availability');

    $location = availabilityLocation($tenant);
    $staff = availabilityStaff($tenant, $location);

    $this->actingAs($owner)
        ->post('http://availability-tenant.velora.test/dashboard/booking/availability/staff/'.$staff->getKey().'/working-hours', [
            'day_of_week' => 1,
            'starts_at' => '17:00',
            'ends_at' => '09:00',
        ])
        ->assertSessionHasErrors(['working_hours']);

    expect(StaffWorkingHour::on('tenant')->count())->toBe(0);
});

test('availability service assignment is denied when services entitlement is missing', function (): void {
    $path = bookingAvailabilityDashboardDatabasePath();
    $this->bookingAvailabilityDashboardDatabasePath = $path;

    $tenant = createBookingAvailabilityDashboardTenant($path);
    $owner = addBookingAvailabilityMember($tenant);
    enableBookingAvailabilityFeature($tenant, 'booking.availability');

    $location = availabilityLocation($tenant);
    $staff = availabilityStaff($tenant, $location);
    $service = availabilityService($tenant);

    $this->actingAs($owner)
        ->post('http://availability-tenant.velora.test/dashboard/booking/availability/staff/'.$staff->getKey().'/services/'.$service->getKey())
        ->assertForbidden();
});
