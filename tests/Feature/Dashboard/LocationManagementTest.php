<?php

use App\Application\Authorization\TenantRbacBootstrapper;
use App\Infrastructure\Tenancy\TenantDatabaseManager;
use App\Models\Location;
use App\Models\PlatformAccount;
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

function locationManagementTestTenantDatabasePath(): string
{
    $directory = storage_path('framework/testing');

    File::ensureDirectoryExists($directory);

    $path = $directory.'/location-management-'.Str::ulid().'.sqlite';
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

function createLocationManagementTenant(
    string $path,
    string $domain = 'location-tenant.velora.test',
    string $slug = 'location-tenant',
): Tenant
{
    $tenant = Tenant::factory()->create([
        'name' => 'Location Tenant',
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

function addLocationMember(Tenant $tenant, string $roleKey = 'owner'): PlatformAccount
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

afterEach(function (): void {
    DB::purge(TenantDatabaseManager::CONNECTION);
    DB::setDefaultConnection('central');

    config([
        'database.connections.tenant_template' => $this->originalTenantTemplate,
    ]);

    if (isset($this->locationManagementTenantDatabasePath) && is_file($this->locationManagementTenantDatabasePath)) {
        unlink($this->locationManagementTenantDatabasePath);
    }
});

test('owner can create update and archive a location', function (): void {
    $path = locationManagementTestTenantDatabasePath();
    $this->locationManagementTenantDatabasePath = $path;

    $tenant = createLocationManagementTenant($path);
    $owner = addLocationMember($tenant);

    $this->actingAs($owner)
        ->post('http://location-tenant.velora.test/dashboard/company/locations', [
            'name' => 'Main Branch',
            'code' => 'main-01',
            'address' => 'Cairo',
            'country_code' => 'eg',
            'city' => 'Cairo',
            'timezone' => 'Africa/Cairo',
            'status' => 'active',
        ])
        ->assertRedirect('/dashboard')
        ->assertSessionHas('status', 'Location created successfully.');

    $manager = app(TenantDatabaseManager::class);
    $manager->connect($tenant);

    try {
        $location = Location::query()->first();

        expect($location)->not->toBeNull()
            ->and($location->code)->toBe('MAIN-01')
            ->and($location->country_code)->toBe('EG');

        $this->actingAs($owner)
            ->patch('http://location-tenant.velora.test/dashboard/company/locations/'.$location->getKey(), [
                'name' => 'Downtown Branch',
                'code' => 'DT-01',
                'address' => 'Downtown Cairo',
                'country_code' => 'EG',
                'city' => 'Cairo',
                'timezone' => 'Africa/Cairo',
                'status' => 'active',
            ])
            ->assertRedirect('/dashboard')
            ->assertSessionHas('status', 'Location updated successfully.');

        expect($location->refresh()->name)->toBe('Downtown Branch')
            ->and($location->code)->toBe('DT-01');

        $this->actingAs($owner)
            ->delete('http://location-tenant.velora.test/dashboard/company/locations/'.$location->getKey())
            ->assertRedirect('/dashboard')
            ->assertSessionHas('status', 'Location archived successfully.');

        expect(Location::query()->find($location->getKey()))->toBeNull()
            ->and(Location::withTrashed()->find($location->getKey())->status)->toBe('inactive');
    } finally {
        $manager->disconnect();
    }
});

test('staff member cannot manage locations', function (): void {
    $path = locationManagementTestTenantDatabasePath();
    $this->locationManagementTenantDatabasePath = $path;

    $tenant = createLocationManagementTenant($path);
    $staff = addLocationMember($tenant, 'staff');

    $this->actingAs($staff)
        ->post('http://location-tenant.velora.test/dashboard/company/locations', [
            'name' => 'Blocked Branch',
            'code' => 'BL-01',
            'timezone' => 'Africa/Cairo',
            'status' => 'active',
        ])
        ->assertForbidden();
});

test('location input validation rejects malformed code country and timezone', function (): void {
    $path = locationManagementTestTenantDatabasePath();
    $this->locationManagementTenantDatabasePath = $path;

    $tenant = createLocationManagementTenant($path);
    $owner = addLocationMember($tenant);

    $this->actingAs($owner)
        ->post('http://location-tenant.velora.test/dashboard/company/locations', [
            'name' => 'Invalid Branch',
            'code' => 'bad code',
            'country_code' => 'EGY',
            'timezone' => 'Not/A-Timezone',
            'status' => 'active',
        ])
        ->assertSessionHasErrors(['code', 'country_code', 'timezone']);

    expect(Location::on('tenant')->count())->toBe(0);
});

test('location from another tenant cannot be addressed through the current tenant host', function (): void {
    $pathA = locationManagementTestTenantDatabasePath();
    $pathB = locationManagementTestTenantDatabasePath();
    $this->locationManagementTenantDatabasePath = $pathA;

    $tenantA = createLocationManagementTenant($pathA, 'location-a.velora.test', 'location-a');
    $tenantB = createLocationManagementTenant($pathB, 'location-b.velora.test', 'location-b');
    $ownerA = addLocationMember($tenantA);

    $manager = app(TenantDatabaseManager::class);
    $manager->connect($tenantB);

    try {
        $foreignLocation = Location::query()->create([
            'name' => 'Tenant B Branch',
            'code' => 'B-01',
            'country_code' => 'EG',
            'city' => 'Cairo',
            'timezone' => 'Africa/Cairo',
            'status' => 'active',
            'metadata' => [],
        ]);
    } finally {
        $manager->disconnect();
        DB::purge(TenantDatabaseManager::CONNECTION);
    }

    $manager->connect($tenantA);

    try {
        $this->actingAs($ownerA)
            ->patch('http://location-a.velora.test/dashboard/company/locations/'.$foreignLocation->getKey(), [
                'name' => 'Hijacked',
                'code' => 'HI-01',
                'country_code' => 'EG',
                'city' => 'Cairo',
                'timezone' => 'Africa/Cairo',
                'status' => 'active',
            ])
            ->assertNotFound();
    } finally {
        $manager->disconnect();
    }

    DB::purge(TenantDatabaseManager::CONNECTION);
    if (is_file($pathB)) {
        unlink($pathB);
    }
});
