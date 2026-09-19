<?php

use App\Application\Authorization\TenantRbacBootstrapper;
use App\Infrastructure\Tenancy\TenantDatabaseManager;
use App\Models\Customer;
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

function customerManagementTestDatabasePath(): string
{
    $directory = storage_path('framework/testing');

    File::ensureDirectoryExists($directory);

    $path = $directory.'/customer-management-'.Str::ulid().'.sqlite';
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

function createCustomerManagementTenant(
    string $path,
    string $domain = 'customer-tenant.velora.test',
    string $slug = 'customer-tenant',
): Tenant {
    $tenant = Tenant::factory()->create([
        'name' => 'Customer Tenant',
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

function addCustomerMember(Tenant $tenant, string $roleKey = 'owner'): PlatformAccount
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

    if (isset($this->customerManagementTenantDatabasePath) && is_file($this->customerManagementTenantDatabasePath)) {
        unlink($this->customerManagementTenantDatabasePath);
    }
});

test('owner can create update and archive a customer', function (): void {
    $path = customerManagementTestDatabasePath();
    $this->customerManagementTenantDatabasePath = $path;

    $tenant = createCustomerManagementTenant($path);
    $owner = addCustomerMember($tenant);

    $this->actingAs($owner)
        ->post('http://customer-tenant.velora.test/dashboard/company/customers', [
            'name' => 'Ahmed Customer',
            'phone' => '+201000000000',
            'email' => 'ahmed@example.test',
            'status' => 'active',
            'source' => 'manual',
            'notes' => 'Initial customer',
        ])
        ->assertRedirect('/dashboard')
        ->assertSessionHas('status', 'Customer created successfully.');

    $manager = app(TenantDatabaseManager::class);
    $manager->connect($tenant);

    try {
        $customer = Customer::query()->first();

        expect($customer)->not->toBeNull()
            ->and($customer->name)->toBe('Ahmed Customer')
            ->and($customer->email)->toBe('ahmed@example.test')
            ->and($customer->source)->toBe('manual');

        $this->actingAs($owner)
            ->patch('http://customer-tenant.velora.test/dashboard/company/customers/'.$customer->getKey(), [
                'name' => 'Ahmed Ali',
                'phone' => '+201011111111',
                'email' => 'ahmed.ali@example.test',
                'status' => 'active',
                'source' => 'booking',
                'notes' => 'Updated',
            ])
            ->assertRedirect('/dashboard')
            ->assertSessionHas('status', 'Customer updated successfully.');

        expect($customer->refresh()->name)->toBe('Ahmed Ali')
            ->and($customer->source)->toBe('booking');

        $this->actingAs($owner)
            ->delete('http://customer-tenant.velora.test/dashboard/company/customers/'.$customer->getKey())
            ->assertRedirect('/dashboard')
            ->assertSessionHas('status', 'Customer archived successfully.');

        expect(Customer::query()->find($customer->getKey()))->toBeNull()
            ->and(Customer::withTrashed()->find($customer->getKey())->status)->toBe('inactive');
    } finally {
        $manager->disconnect();
    }
});

test('staff role cannot manage customers', function (): void {
    $path = customerManagementTestDatabasePath();
    $this->customerManagementTenantDatabasePath = $path;

    $tenant = createCustomerManagementTenant($path);
    $staff = addCustomerMember($tenant, 'viewer');

    $this->actingAs($staff)
        ->post('http://customer-tenant.velora.test/dashboard/company/customers', [
            'name' => 'Blocked Customer',
            'status' => 'active',
        ])
        ->assertForbidden();
});

test('customer validation rejects malformed email and status', function (): void {
    $path = customerManagementTestDatabasePath();
    $this->customerManagementTenantDatabasePath = $path;

    $tenant = createCustomerManagementTenant($path);
    $owner = addCustomerMember($tenant);

    $this->actingAs($owner)
        ->post('http://customer-tenant.velora.test/dashboard/company/customers', [
            'name' => 'Invalid Customer',
            'email' => 'not-an-email',
            'status' => 'unknown',
        ])
        ->assertSessionHasErrors(['email', 'status']);

    $manager = app(TenantDatabaseManager::class);
    $manager->connect($tenant);

    try {
        expect(Customer::query()->count())->toBe(0);
    } finally {
        $manager->disconnect();
    }
});

test('customer record from another tenant cannot be addressed through current tenant host', function (): void {
    $pathA = customerManagementTestDatabasePath();
    $pathB = customerManagementTestDatabasePath();
    $this->customerManagementTenantDatabasePath = $pathA;

    $tenantA = createCustomerManagementTenant($pathA, 'customer-a.velora.test', 'customer-a');
    $tenantB = createCustomerManagementTenant($pathB, 'customer-b.velora.test', 'customer-b');
    $ownerA = addCustomerMember($tenantA);

    $manager = app(TenantDatabaseManager::class);
    $manager->connect($tenantB);

    try {
        $foreignCustomer = Customer::query()->create([
            'name' => 'Tenant B Customer',
            'email' => 'b@example.test',
            'status' => 'active',
            'source' => 'test',
        ]);
    } finally {
        $manager->disconnect();
        DB::purge(TenantDatabaseManager::CONNECTION);
    }

    $this->actingAs($ownerA)
        ->patch('http://customer-a.velora.test/dashboard/company/customers/'.$foreignCustomer->getKey(), [
            'name' => 'Hijacked',
            'email' => 'hijacked@example.test',
            'status' => 'active',
            'source' => 'test',
        ])
        ->assertNotFound();

    DB::purge(TenantDatabaseManager::CONNECTION);
    if (is_file($pathB)) {
        unlink($pathB);
    }
});
