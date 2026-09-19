<?php

use App\Application\Authorization\TenantRbacBootstrapper;
use App\Application\Company\TenantMembershipManager;
use App\Infrastructure\Tenancy\TenantDatabaseManager;
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

function membershipManagementTestDatabasePath(): string
{
    $directory = storage_path('framework/testing');

    File::ensureDirectoryExists($directory);

    $path = $directory.'/membership-management-'.Str::ulid().'.sqlite';
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

function createMembershipManagementTenant(
    string $path,
    string $domain = 'members-tenant.velora.test',
    string $slug = 'members-tenant',
): Tenant {
    $tenant = Tenant::factory()->create([
        'name' => 'Members Tenant',
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

function addMembershipManagementMember(Tenant $tenant, string $roleKey = 'owner', ?string $email = null): PlatformAccount
{
    $account = PlatformAccount::factory()->create([
        'email' => $email ?? fake()->unique()->safeEmail(),
    ]);

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

    if (isset($this->membershipManagementTenantDatabasePath) && is_file($this->membershipManagementTenantDatabasePath)) {
        unlink($this->membershipManagementTenantDatabasePath);
    }
});

test('owner can add an existing platform account to the tenant and assign a tenant role', function (): void {
    $path = membershipManagementTestDatabasePath();
    $this->membershipManagementTenantDatabasePath = $path;

    $tenant = createMembershipManagementTenant($path);
    $owner = addMembershipManagementMember($tenant, 'owner', 'owner@example.test');
    $newAccount = PlatformAccount::factory()->create([
        'name' => 'New Member',
        'email' => 'new-member@example.test',
    ]);

    $this->actingAs($owner)
        ->post('http://members-tenant.velora.test/dashboard/company/users', [
            'email' => $newAccount->email,
            'role_key' => 'manager',
        ])
        ->assertRedirect('/dashboard')
        ->assertSessionHas('status', 'Tenant member added successfully.');

    $membership = TenantMembership::query()
        ->where('tenant_id', $tenant->getKey())
        ->where('account_id', $newAccount->getKey())
        ->first();

    expect($membership)->not->toBeNull()
        ->and($membership->role_key)->toBe('manager')
        ->and($membership->status)->toBe('active');

    setPermissionsTeamId($tenant->getKey());
    try {
        expect($newAccount->fresh()->hasRole('manager'))->toBeTrue();
    } finally {
        setPermissionsTeamId(null);
    }
});

test('owner can change a membership role and deactivate it', function (): void {
    $path = membershipManagementTestDatabasePath();
    $this->membershipManagementTenantDatabasePath = $path;

    $tenant = createMembershipManagementTenant($path);
    $owner = addMembershipManagementMember($tenant);
    $member = addMembershipManagementMember($tenant, 'staff', 'staff@example.test');

    $membership = TenantMembership::query()
        ->where('tenant_id', $tenant->getKey())
        ->where('account_id', $member->getKey())
        ->firstOrFail();

    $this->actingAs($owner)
        ->patch('http://members-tenant.velora.test/dashboard/company/users/'.$membership->getKey(), [
            'role_key' => 'manager',
            'status' => 'active',
        ])
        ->assertRedirect('/dashboard');

    expect($membership->refresh()->role_key)->toBe('manager');

    $this->actingAs($owner)
        ->delete('http://members-tenant.velora.test/dashboard/company/users/'.$membership->getKey())
        ->assertRedirect('/dashboard');

    expect($membership->refresh()->status)->toBe('inactive');

    setPermissionsTeamId($tenant->getKey());
    try {
        expect($member->fresh()->hasAnyRole(Role::query()->where('tenant_id', $tenant->getKey())->pluck('name')->all()))
            ->toBeFalse();
    } finally {
        setPermissionsTeamId(null);
    }
});

test('non-manager tenant members cannot manage memberships', function (): void {
    $path = membershipManagementTestDatabasePath();
    $this->membershipManagementTenantDatabasePath = $path;

    $tenant = createMembershipManagementTenant($path);
    $owner = addMembershipManagementMember($tenant);
    $staff = addMembershipManagementMember($tenant, 'staff', 'staff-manager-test@example.test');

    $membership = TenantMembership::query()
        ->where('tenant_id', $tenant->getKey())
        ->where('account_id', $staff->getKey())
        ->firstOrFail();

    $this->actingAs($staff)
        ->patch('http://members-tenant.velora.test/dashboard/company/users/'.$membership->getKey(), [
            'role_key' => 'manager',
        ])
        ->assertForbidden();
});

test('the last active owner cannot be demoted or deactivated', function (): void {
    $path = membershipManagementTestDatabasePath();
    $this->membershipManagementTenantDatabasePath = $path;

    $tenant = createMembershipManagementTenant($path);
    $owner = addMembershipManagementMember($tenant);

    $membership = TenantMembership::query()
        ->where('tenant_id', $tenant->getKey())
        ->where('account_id', $owner->getKey())
        ->firstOrFail();

    $this->actingAs($owner)
        ->patch('http://members-tenant.velora.test/dashboard/company/users/'.$membership->getKey(), [
            'role_key' => 'admin',
            'status' => 'active',
        ])
        ->assertSessionHasErrors('role_key');

    expect($membership->refresh()->role_key)->toBe('owner')
        ->and($membership->status)->toBe('active');
});

test('membership from another tenant cannot be updated through the current tenant host', function (): void {
    $pathA = membershipManagementTestDatabasePath();
    $pathB = membershipManagementTestDatabasePath();
    $this->membershipManagementTenantDatabasePath = $pathA;

    $tenantA = createMembershipManagementTenant($pathA, 'members-a.velora.test', 'members-a');
    $tenantB = createMembershipManagementTenant($pathB, 'members-b.velora.test', 'members-b');
    $ownerA = addMembershipManagementMember($tenantA);
    $ownerB = addMembershipManagementMember($tenantB, 'owner', 'owner-b@example.test');

    $foreignMembership = TenantMembership::query()
        ->where('tenant_id', $tenantB->getKey())
        ->where('account_id', $ownerB->getKey())
        ->firstOrFail();

    $this->actingAs($ownerA)
        ->patch('http://members-a.velora.test/dashboard/company/users/'.$foreignMembership->getKey(), [
            'role_key' => 'admin',
        ])
        ->assertForbidden();

    DB::purge(TenantDatabaseManager::CONNECTION);
    if (is_file($pathB)) {
        unlink($pathB);
    }
});
