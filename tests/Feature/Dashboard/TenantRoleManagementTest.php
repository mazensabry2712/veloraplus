<?php

use App\Application\Authorization\TenantRbacBootstrapper;
use App\Infrastructure\Tenancy\TenantDatabaseManager;
use App\Application\Authorization\TenantRoleManager;
use App\Domain\Tenancy\TenantContext;
use App\Models\PlatformAccount;
use App\Models\Role;
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

function tenantRoleManagementTestDatabasePath(): string
{
    $directory = storage_path('framework/testing');

    File::ensureDirectoryExists($directory);

    $path = $directory.'/role-management-'.Str::ulid().'.sqlite';
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

function createTenantRoleManagementTenant(
    string $path,
    string $domain = 'roles-tenant.velora.test',
    string $slug = 'roles-tenant',
): Tenant {
    $tenant = Tenant::factory()->create([
        'name' => 'Roles Tenant',
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

    return $tenant;
}

function addTenantRoleManagementMember(Tenant $tenant, string $roleKey = 'owner'): PlatformAccount
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

function migrateRoleManagementDatabase(string $path, Tenant $tenant): void
{
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

afterEach(function (): void {
    DB::purge(TenantDatabaseManager::CONNECTION);
    DB::setDefaultConnection('central');

    config([
        'database.connections.tenant_template' => $this->originalTenantTemplate,
    ]);

    if (isset($this->tenantRoleManagementDatabasePath) && is_file($this->tenantRoleManagementDatabasePath)) {
        unlink($this->tenantRoleManagementDatabasePath);
    }

    setPermissionsTeamId(null);
});

test('owner can create and update a custom tenant role', function (): void {
    $path = tenantRoleManagementTestDatabasePath();
    $this->tenantRoleManagementDatabasePath = $path;

    $tenant = createTenantRoleManagementTenant($path);
    $owner = addTenantRoleManagementMember($tenant);

    $this->actingAs($owner)
        ->post('http://roles-tenant.velora.test/dashboard/company/roles', [
            'name' => 'Front Desk',
            'permissions' => ['customers.view', 'booking.appointments.view'],
        ])
        ->assertRedirect('/dashboard')
        ->assertSessionHas('status', 'Custom role created successfully.');

    $role = Role::query()
        ->where('tenant_id', $tenant->getKey())
        ->where('name', 'Front Desk')
        ->firstOrFail();

    setPermissionsTeamId($tenant->getKey());

    try {
        expect($role->hasPermissionTo('customers.view'))->toBeTrue()
            ->and($role->hasPermissionTo('booking.appointments.view'))->toBeTrue();
    } finally {
        setPermissionsTeamId(null);
    }

    $this->actingAs($owner)
        ->patch('http://roles-tenant.velora.test/dashboard/company/roles/'.$role->getKey(), [
            'permissions' => ['customers.view'],
        ])
        ->assertRedirect('/dashboard');

    $role->refresh();

    setPermissionsTeamId($tenant->getKey());

    try {
        expect($role->hasPermissionTo('customers.view'))->toBeTrue()
            ->and($role->hasPermissionTo('booking.appointments.view'))->toBeFalse();
    } finally {
        setPermissionsTeamId(null);
    }
});

test('system roles cannot be changed or deleted', function (): void {
    $path = tenantRoleManagementTestDatabasePath();
    $this->tenantRoleManagementDatabasePath = $path;

    $tenant = createTenantRoleManagementTenant($path);
    $owner = addTenantRoleManagementMember($tenant);
    $systemRole = Role::query()
        ->where('tenant_id', $tenant->getKey())
        ->where('name', 'manager')
        ->firstOrFail();

    $this->actingAs($owner)
        ->patch('http://roles-tenant.velora.test/dashboard/company/roles/'.$systemRole->getKey(), [
            'permissions' => ['customers.view'],
        ])
        ->assertSessionHasErrors('permissions');

    expect($systemRole->refresh()->name)->toBe('manager');

    $this->actingAs($owner)
        ->delete('http://roles-tenant.velora.test/dashboard/company/roles/'.$systemRole->getKey())
        ->assertSessionHasErrors('role');
});

test('role with an active or inactive membership cannot be deleted', function (): void {
    $path = tenantRoleManagementTestDatabasePath();
    $this->tenantRoleManagementDatabasePath = $path;

    $tenant = createTenantRoleManagementTenant($path);
    $owner = addTenantRoleManagementMember($tenant);

    $role = app(TenantRoleManager::class)->create([
        'name' => 'Reception',
        'permissions' => ['customers.view'],
    ]);

    TenantMembership::create([
        'tenant_id' => $tenant->getKey(),
        'account_id' => PlatformAccount::factory()->create()->getKey(),
        'role_key' => $role->name,
        'status' => 'inactive',
    ]);

    $this->actingAs($owner)
        ->delete('http://roles-tenant.velora.test/dashboard/company/roles/'.$role->getKey())
        ->assertSessionHasErrors('role');

    expect(Role::query()->whereKey($role->getKey())->exists())->toBeTrue();
});

test('non-manager members cannot manage custom roles', function (): void {
    $path = tenantRoleManagementTestDatabasePath();
    $this->tenantRoleManagementDatabasePath = $path;

    $tenant = createTenantRoleManagementTenant($path);
    $staff = addTenantRoleManagementMember($tenant, 'staff');

    $this->actingAs($staff)
        ->post('http://roles-tenant.velora.test/dashboard/company/roles', [
            'name' => 'Blocked',
            'permissions' => ['customers.view'],
        ])
        ->assertForbidden();
});

test('custom role cannot be mutated from another tenant context', function (): void {
    $pathA = tenantRoleManagementTestDatabasePath();
    $pathB = tenantRoleManagementTestDatabasePath();
    $this->tenantRoleManagementDatabasePath = $pathA;

    $tenantA = createTenantRoleManagementTenant($pathA, 'roles-a.velora.test', 'roles-a');
    $tenantB = createTenantRoleManagementTenant($pathB, 'roles-b.velora.test', 'roles-b');

    $ownerA = addTenantRoleManagementMember($tenantA);
    addTenantRoleManagementMember($tenantB);

    app(TenantContext::class)->set($tenantB);
    app(TenantRoleManager::class)->create([
        'name' => 'Tenant B Role',
        'permissions' => ['customers.view'],
    ]);
    $roleB = Role::query()
        ->where('tenant_id', $tenantB->getKey())
        ->where('name', 'Tenant B Role')
        ->firstOrFail();

    $this->actingAs($ownerA)
        ->patch('http://roles-a.velora.test/dashboard/company/roles/'.$roleB->getKey(), [
            'permissions' => ['customers.manage'],
        ])
        ->assertForbidden();

    DB::purge(TenantDatabaseManager::CONNECTION);
    if (is_file($pathB)) {
        unlink($pathB);
    }
});
