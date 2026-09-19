<?php

use App\Application\Authorization\TenantRbacBootstrapper;
use App\Infrastructure\Tenancy\TenantDatabaseManager;
use App\Models\Location;
use App\Models\PlatformAccount;
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

function staffManagementTestTenantDatabasePath(): string
{
    $directory = storage_path('framework/testing');

    File::ensureDirectoryExists($directory);

    $path = $directory.'/staff-management-'.Str::ulid().'.sqlite';
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

function createStaffManagementTenant(
    string $path,
    string $domain = 'staff-tenant.velora.test',
    string $slug = 'staff-tenant',
): Tenant {
    $tenant = Tenant::factory()->create([
        'name' => 'Staff Tenant',
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

function addStaffMember(Tenant $tenant, string $roleKey = 'owner'): PlatformAccount
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

function staffManagementLocation(Tenant $tenant, string $name = 'Main Branch'): Location
{
    $manager = app(TenantDatabaseManager::class);
    $manager->connect($tenant);

    try {
        return Location::query()->create([
            'name' => $name,
            'code' => strtoupper(Str::slug($name).'-01'),
            'address' => 'Cairo',
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

afterEach(function (): void {
    DB::purge(TenantDatabaseManager::CONNECTION);
    DB::setDefaultConnection('central');

    config([
        'database.connections.tenant_template' => $this->originalTenantTemplate,
    ]);

    if (isset($this->staffManagementTenantDatabasePath) && is_file($this->staffManagementTenantDatabasePath)) {
        unlink($this->staffManagementTenantDatabasePath);
    }
});

test('owner can create update and archive a staff member', function (): void {
    $path = staffManagementTestTenantDatabasePath();
    $this->staffManagementTenantDatabasePath = $path;

    $tenant = createStaffManagementTenant($path);
    $owner = addStaffMember($tenant);
    $location = staffManagementLocation($tenant);

    $this->actingAs($owner)
        ->post('http://staff-tenant.velora.test/dashboard/company/staff', [
            'name' => 'Dr. Ahmed',
            'phone' => '+201000000000',
            'email' => 'ahmed@example.test',
            'location_id' => $location->getKey(),
            'status' => 'active',
        ])
        ->assertRedirect('/dashboard')
        ->assertSessionHas('status', 'Staff member created successfully.');

    $manager = app(TenantDatabaseManager::class);
    $manager->connect($tenant);

    try {
        $staff = Staff::query()->first();

        expect($staff)->not->toBeNull()
            ->and($staff->name)->toBe('Dr. Ahmed')
            ->and($staff->email)->toBe('ahmed@example.test')
            ->and($staff->location_id)->toBe($location->getKey());

        $this->actingAs($owner)
            ->patch('http://staff-tenant.velora.test/dashboard/company/staff/'.$staff->getKey(), [
                'name' => 'Dr. Ahmed Ali',
                'phone' => '+201011111111',
                'email' => 'ahmed.ali@example.test',
                'location_id' => $location->getKey(),
                'status' => 'active',
            ])
            ->assertRedirect('/dashboard')
            ->assertSessionHas('status', 'Staff member updated successfully.');

        expect($staff->refresh()->name)->toBe('Dr. Ahmed Ali')
            ->and($staff->email)->toBe('ahmed.ali@example.test');

        $this->actingAs($owner)
            ->delete('http://staff-tenant.velora.test/dashboard/company/staff/'.$staff->getKey())
            ->assertRedirect('/dashboard')
            ->assertSessionHas('status', 'Staff member archived successfully.');

        expect(Staff::query()->find($staff->getKey()))->toBeNull()
            ->and(Staff::withTrashed()->find($staff->getKey())->status)->toBe('inactive');
    } finally {
        $manager->disconnect();
    }
});

test('staff role cannot manage staff records', function (): void {
    $path = staffManagementTestTenantDatabasePath();
    $this->staffManagementTenantDatabasePath = $path;

    $tenant = createStaffManagementTenant($path);
    $staffAccount = addStaffMember($tenant, 'staff');

    $this->actingAs($staffAccount)
        ->post('http://staff-tenant.velora.test/dashboard/company/staff', [
            'name' => 'Blocked Staff',
            'status' => 'active',
        ])
        ->assertForbidden();
});

test('staff validation rejects inactive locations and malformed email', function (): void {
    $path = staffManagementTestTenantDatabasePath();
    $this->staffManagementTenantDatabasePath = $path;

    $tenant = createStaffManagementTenant($path);
    $owner = addStaffMember($tenant);
    $location = staffManagementLocation($tenant, 'Inactive Branch');

    $manager = app(TenantDatabaseManager::class);
    $manager->connect($tenant);

    try {
        $location->forceFill(['status' => 'inactive'])->save();
    } finally {
        $manager->disconnect();
    }

    $this->actingAs($owner)
        ->post('http://staff-tenant.velora.test/dashboard/company/staff', [
            'name' => 'Invalid Staff',
            'email' => 'not-an-email',
            'location_id' => $location->getKey(),
            'status' => 'active',
        ])
        ->assertSessionHasErrors(['email', 'location_id']);

    $manager->connect($tenant);

    try {
        expect(Staff::query()->count())->toBe(0);
    } finally {
        $manager->disconnect();
    }
});

test('staff cannot be assigned a location from another tenant', function (): void {
    $pathA = staffManagementTestTenantDatabasePath();
    $pathB = staffManagementTestTenantDatabasePath();
    $this->staffManagementTenantDatabasePath = $pathA;

    $tenantA = createStaffManagementTenant($pathA, 'staff-a.velora.test', 'staff-a');
    $tenantB = createStaffManagementTenant($pathB, 'staff-b.velora.test', 'staff-b');
    $ownerA = addStaffMember($tenantA);
    $foreignLocation = staffManagementLocation($tenantB, 'Tenant B Branch');

    $this->actingAs($ownerA)
        ->post('http://staff-a.velora.test/dashboard/company/staff', [
            'name' => 'Cross Tenant',
            'location_id' => $foreignLocation->getKey(),
            'status' => 'active',
        ])
        ->assertSessionHasErrors(['location_id']);

    DB::purge(TenantDatabaseManager::CONNECTION);
    if (is_file($pathB)) {
        unlink($pathB);
    }
});
