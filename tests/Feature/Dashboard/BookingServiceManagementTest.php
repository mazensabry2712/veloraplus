<?php

use App\Application\Authorization\TenantRbacBootstrapper;
use App\Application\Booking\ServiceManager;
use App\Application\Entitlements\EntitlementService;
use App\Infrastructure\Tenancy\TenantDatabaseManager;
use App\Models\Feature;
use App\Models\Module;
use App\Models\PlatformAccount;
use App\Models\Service;
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

function bookingServiceDashboardTestDatabasePath(): string
{
    $directory = storage_path('framework/testing');

    File::ensureDirectoryExists($directory);

    $path = $directory.'/dashboard-booking-services-'.Str::ulid().'.sqlite';
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

function createBookingServiceDashboardTenant(
    string $path,
    string $domain = 'booking-services.velora.test',
    string $slug = 'booking-services',
): Tenant {
    $tenant = Tenant::factory()->create([
        'name' => 'Booking Services Tenant',
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

function addBookingServiceDashboardMember(Tenant $tenant, string $roleKey = 'owner'): PlatformAccount
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

function enableBookingServicesFeature(Tenant $tenant): void
{
    $module = Module::factory()->create([
        'key' => 'booking',
        'name' => 'Booking',
        'status' => 'active',
    ]);

    $feature = Feature::factory()->create([
        'module_id' => $module->getKey(),
        'key' => 'booking.services',
        'name' => 'Booking Services',
        'status' => 'active',
    ]);

    app(EntitlementService::class)->grant($tenant, $feature);
}

afterEach(function (): void {
    DB::purge(TenantDatabaseManager::CONNECTION);
    DB::setDefaultConnection('central');
    app(\App\Domain\Tenancy\TenantContext::class)->clear();
    setPermissionsTeamId(null);

    config([
        'database.connections.tenant_template' => $this->originalTenantTemplate,
    ]);

    if (isset($this->bookingServiceDashboardTestDatabasePath) && is_file($this->bookingServiceDashboardTestDatabasePath)) {
        unlink($this->bookingServiceDashboardTestDatabasePath);
    }
});

test('owner can create update and archive a booking service through the dashboard backend', function (): void {
    $path = bookingServiceDashboardTestDatabasePath();
    $this->bookingServiceDashboardTestDatabasePath = $path;

    $tenant = createBookingServiceDashboardTenant($path);
    $owner = addBookingServiceDashboardMember($tenant);
    enableBookingServicesFeature($tenant);

    $this->actingAs($owner)
        ->post('http://booking-services.velora.test/dashboard/booking/services', [
            'name' => 'Dental Consultation',
            'description' => 'Initial consultation',
            'duration_minutes' => 60,
            'buffer_before_minutes' => 10,
            'buffer_after_minutes' => 5,
            'price_minor' => 50000,
            'currency' => 'egp',
            'deposit_amount_minor' => 10000,
            'status' => 'active',
            'online_bookable' => true,
            'capacity' => 1,
        ])
        ->assertRedirect('/dashboard')
        ->assertSessionHas('status', 'Booking service created successfully.');

    $manager = app(TenantDatabaseManager::class);
    $manager->connect($tenant);

    try {
        $service = Service::query()->firstOrFail();

        expect($service->slug)->toBe('dental-consultation')
            ->and($service->currency)->toBe('EGP')
            ->and($service->price_minor)->toBe(50000);

        $this->actingAs($owner)
            ->patch('http://booking-services.velora.test/dashboard/booking/services/'.$service->getKey(), [
                'name' => 'Extended Consultation',
                'duration_minutes' => 90,
                'price_minor' => 75000,
                'capacity' => 2,
            ])
            ->assertRedirect('/dashboard')
            ->assertSessionHas('status', 'Booking service updated successfully.');

        expect($service->refresh()->name)->toBe('Extended Consultation')
            ->and($service->price_minor)->toBe(75000)
            ->and($service->capacity)->toBe(2);

        $this->actingAs($owner)
            ->delete('http://booking-services.velora.test/dashboard/booking/services/'.$service->getKey())
            ->assertRedirect('/dashboard')
            ->assertSessionHas('status', 'Booking service archived successfully.');

        expect(Service::query()->find($service->getKey()))->toBeNull()
            ->and(Service::withTrashed()->find($service->getKey())->status->value)->toBe('inactive');
    } finally {
        $manager->disconnect();
    }
});

test('viewer cannot manage booking services', function (): void {
    $path = bookingServiceDashboardTestDatabasePath();
    $this->bookingServiceDashboardTestDatabasePath = $path;

    $tenant = createBookingServiceDashboardTenant($path);
    $viewer = addBookingServiceDashboardMember($tenant, 'viewer');
    enableBookingServicesFeature($tenant);

    $this->actingAs($viewer)
        ->post('http://booking-services.velora.test/dashboard/booking/services', [
            'name' => 'Blocked',
            'duration_minutes' => 30,
            'price_minor' => 1000,
            'currency' => 'EGP',
            'capacity' => 1,
            'online_bookable' => true,
            'status' => 'active',
        ])
        ->assertForbidden();
});

test('booking service entitlement is required before dashboard mutation', function (): void {
    $path = bookingServiceDashboardTestDatabasePath();
    $this->bookingServiceDashboardTestDatabasePath = $path;

    $tenant = createBookingServiceDashboardTenant($path);
    $owner = addBookingServiceDashboardMember($tenant);

    $this->actingAs($owner)
        ->post('http://booking-services.velora.test/dashboard/booking/services', [
            'name' => 'Not Entitled',
            'duration_minutes' => 30,
            'price_minor' => 1000,
            'currency' => 'EGP',
            'capacity' => 1,
            'online_bookable' => true,
            'status' => 'active',
        ])
        ->assertForbidden();
});

test('booking service validation rejects malformed values before manager persistence', function (): void {
    $path = bookingServiceDashboardTestDatabasePath();
    $this->bookingServiceDashboardTestDatabasePath = $path;

    $tenant = createBookingServiceDashboardTenant($path);
    $owner = addBookingServiceDashboardMember($tenant);
    enableBookingServicesFeature($tenant);

    $this->actingAs($owner)
        ->post('http://booking-services.velora.test/dashboard/booking/services', [
            'name' => '',
            'duration_minutes' => 0,
            'price_minor' => -1,
            'currency' => 'EG',
            'capacity' => 0,
            'online_bookable' => true,
            'status' => 'active',
        ])
        ->assertSessionHasErrors([
            'name',
            'duration_minutes',
            'price_minor',
            'currency',
            'capacity',
        ]);

    $manager = app(TenantDatabaseManager::class);
    $manager->connect($tenant);

    try {
        expect(Service::query()->count())->toBe(0);
    } finally {
        $manager->disconnect();
    }
});

test('booking service route binding is tenant aware', function (): void {
    $pathA = bookingServiceDashboardTestDatabasePath();
    $pathB = bookingServiceDashboardTestDatabasePath();
    $this->bookingServiceDashboardTestDatabasePath = $pathA;

    $tenantA = createBookingServiceDashboardTenant($pathA, 'booking-services-a.velora.test', 'booking-services-a');
    $tenantB = createBookingServiceDashboardTenant($pathB, 'booking-services-b.velora.test', 'booking-services-b');
    $ownerA = addBookingServiceDashboardMember($tenantA);
    enableBookingServicesFeature($tenantA);
    enableBookingServicesFeature($tenantB);

    $manager = app(TenantDatabaseManager::class);
    $manager->connect($tenantB);

    try {
        $foreignService = app(ServiceManager::class)->create([
            'name' => 'Tenant B Service',
            'duration_minutes' => 30,
            'price_minor' => 1000,
            'currency' => 'EGP',
            'deposit_amount_minor' => 0,
            'capacity' => 1,
        ]);
    } finally {
        $manager->disconnect();
        DB::purge(TenantDatabaseManager::CONNECTION);
    }

    $this->actingAs($ownerA)
        ->patch('http://booking-services-a.velora.test/dashboard/booking/services/'.$foreignService->getKey(), [
            'name' => 'Hijacked Service',
        ])
        ->assertNotFound();

    DB::purge(TenantDatabaseManager::CONNECTION);
    if (is_file($pathB)) {
        unlink($pathB);
    }
});
