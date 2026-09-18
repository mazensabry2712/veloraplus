<?php

use App\Domain\Tenancy\TenantContext;
use App\Infrastructure\Tenancy\TenantDatabaseManager;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TenantMembership;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->originalTenantTemplate = config('database.connections.tenant_template');

    DB::setDefaultConnection('central');

    expect(Artisan::call('migrate:fresh', [
        '--database' => 'central',
        '--force' => true,
    ]))->toBe(0);
});

afterEach(function () {
    DB::purge(TenantDatabaseManager::CONNECTION);
    DB::setDefaultConnection('central');

    config([
        'database.connections.tenant_template' => $this->originalTenantTemplate,
    ]);
});

function tenantCoreTestDatabase(): string
{
    $directory = storage_path('framework/testing');

    File::ensureDirectoryExists($directory);

    $path = $directory.'/tenant-'.Str::ulid().'.sqlite';

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

function migrateTenantCoreTestDatabase(string $path): void
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

function cleanupTenantCoreTestDatabase(string $path): void
{
    DB::purge(TenantDatabaseManager::CONNECTION);

    if (is_file($path)) {
        unlink($path);
    }
}

test('tenant core migrations create the company runtime schema', function () {
    $path = tenantCoreTestDatabase();

    try {
        migrateTenantCoreTestDatabase($path);

        $tenant = new Tenant;
        $tenant->database_name = $path;

        $manager = app(TenantDatabaseManager::class);
        $manager->connect($tenant);

        expect(Schema::connection(TenantDatabaseManager::CONNECTION)->hasTable('tenant_runtime'))->toBeTrue()
            ->and(Schema::connection(TenantDatabaseManager::CONNECTION)->hasTable('company_settings'))->toBeTrue()
            ->and(Schema::connection(TenantDatabaseManager::CONNECTION)->hasTable('locations'))->toBeTrue()
            ->and(Schema::connection(TenantDatabaseManager::CONNECTION)->hasTable('staff'))->toBeTrue()
            ->and(Schema::connection(TenantDatabaseManager::CONNECTION)->hasTable('customers'))->toBeTrue()
            ->and((new CompanySetting)->getConnectionName())->toBe(TenantDatabaseManager::CONNECTION)
            ->and((new Location)->getConnectionName())->toBe(TenantDatabaseManager::CONNECTION)
            ->and((new Staff)->getConnectionName())->toBe(TenantDatabaseManager::CONNECTION)
            ->and((new Customer)->getConnectionName())->toBe(TenantDatabaseManager::CONNECTION);

        $manager->disconnect();
    } finally {
        cleanupTenantCoreTestDatabase($path);
    }
});

test('tenant core models can create company settings location staff and customer records', function () {
    $path = tenantCoreTestDatabase();

    try {
        migrateTenantCoreTestDatabase($path);

        $tenant = new Tenant;
        $tenant->database_name = $path;

        $manager = app(TenantDatabaseManager::class);
        $manager->connect($tenant);

        $setting = CompanySetting::create([
            'key' => 'company.name',
            'value' => 'Velora Clinic',
            'type' => 'string',
        ]);

        $location = Location::create([
            'name' => 'Main Branch',
            'code' => 'MAIN',
            'address' => 'Cairo',
            'country_code' => 'EG',
            'city' => 'Cairo',
            'timezone' => 'Africa/Cairo',
            'status' => 'active',
            'metadata' => [],
        ]);

        $staff = Staff::create([
            'location_id' => $location->getKey(),
            'name' => 'Clinic Manager',
            'phone' => '+201000000000',
            'email' => 'manager@example.test',
            'status' => 'active',
            'metadata' => [],
        ]);

        $customer = Customer::create([
            'name' => 'Customer One',
            'phone' => '+201100000000',
            'email' => 'customer@example.test',
            'status' => 'active',
            'source' => 'manual',
            'metadata' => [],
        ]);

        expect($setting->exists)->toBeTrue()
            ->and($location->exists)->toBeTrue()
            ->and($staff->location->is($location))->toBeTrue()
            ->and($customer->exists)->toBeTrue();

        $manager->disconnect();
    } finally {
        cleanupTenantCoreTestDatabase($path);
    }
});

test('tenant middleware initializes and clears tenant context for a real request', function () {
    $path = tenantCoreTestDatabase();

    try {
        migrateTenantCoreTestDatabase($path);

        $tenant = Tenant::factory()->create([
            'name' => 'Request Tenant',
            'slug' => 'request-tenant',
            'database_name' => $path,
            'database_host' => null,
            'database_port' => null,
            'status' => 'active',
            'database_status' => 'ready',
        ]);

        TenantDomain::create([
            'tenant_id' => $tenant->getKey(),
            'domain' => 'request-tenant.velora.test',
            'type' => 'subdomain',
            'is_primary' => true,
            'status' => 'active',
            'verified_at' => now(),
        ]);

        Route::middleware('tenant')->get('/__tenant-context-test', function (TenantContext $context) {
            return response()->json([
                'tenant_id' => $context->current()->getKey(),
                'default_connection' => DB::getDefaultConnection(),
                'runtime_exists' => Schema::connection(TenantDatabaseManager::CONNECTION)->hasTable('tenant_runtime'),
            ]);
        });

        $this->get('http://request-tenant.velora.test/__tenant-context-test')
            ->assertOk()
            ->assertJson([
                'tenant_id' => $tenant->getKey(),
                'default_connection' => TenantDatabaseManager::CONNECTION,
                'runtime_exists' => true,
            ]);

        expect(DB::getDefaultConnection())->toBe('central')
            ->and(app(TenantContext::class)->check())->toBeFalse();
    } finally {
        cleanupTenantCoreTestDatabase($path);
    }
});

test('tenant databases remain isolated from each other', function () {
    $pathA = tenantCoreTestDatabase();
    $pathB = tenantCoreTestDatabase();

    try {
        migrateTenantCoreTestDatabase($pathA);
        migrateTenantCoreTestDatabase($pathB);

        $manager = app(TenantDatabaseManager::class);

        $tenantA = new Tenant;
        $tenantA->database_name = $pathA;

        $manager->connect($tenantA);
        $customerA = Customer::create([
            'name' => 'Tenant A Customer',
            'email' => 'a@example.test',
            'status' => 'active',
            'source' => 'test',
            'metadata' => [],
        ]);
        $manager->disconnect();

        $tenantB = new Tenant;
        $tenantB->database_name = $pathB;

        $manager->connect($tenantB);
        $customerB = Customer::create([
            'name' => 'Tenant B Customer',
            'email' => 'b@example.test',
            'status' => 'active',
            'source' => 'test',
            'metadata' => [],
        ]);
        $manager->disconnect();

        $manager->connect($tenantA);

        expect(Customer::query()->whereKey($customerA->getKey())->exists())->toBeTrue()
            ->and(Customer::query()->whereKey($customerB->getKey())->exists())->toBeFalse();

        $manager->disconnect();

        $manager->connect($tenantB);

        expect(Customer::query()->whereKey($customerB->getKey())->exists())->toBeTrue()
            ->and(Customer::query()->whereKey($customerA->getKey())->exists())->toBeFalse();

        $manager->disconnect();
    } finally {
        cleanupTenantCoreTestDatabase($pathA);
        cleanupTenantCoreTestDatabase($pathB);
    }
});

test('tenant membership middleware allows active members and blocks non-members', function () {
    $path = tenantCoreTestDatabase();

    try {
        migrateTenantCoreTestDatabase($path);

        $tenant = Tenant::factory()->create([
            'name' => 'Membership Tenant',
            'slug' => 'membership-tenant',
            'database_name' => $path,
            'database_host' => null,
            'database_port' => null,
            'status' => 'active',
            'database_status' => 'ready',
        ]);

        TenantDomain::create([
            'tenant_id' => $tenant->getKey(),
            'domain' => 'membership-tenant.velora.test',
            'type' => 'subdomain',
            'is_primary' => true,
            'status' => 'active',
            'verified_at' => now(),
        ]);

        $member = App\Models\PlatformAccount::factory()->create();

        TenantMembership::create([
            'tenant_id' => $tenant->getKey(),
            'account_id' => $member->getKey(),
            'role_key' => 'owner',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        Route::middleware(['tenant', 'tenant.member'])->get('/__tenant-membership-test', function () {
            return response()->json(['ok' => true]);
        });

        $this->actingAs($member)
            ->get('http://membership-tenant.velora.test/__tenant-membership-test')
            ->assertOk()
            ->assertJson(['ok' => true]);

        $outsider = App\Models\PlatformAccount::factory()->create();

        $this->actingAs($outsider)
            ->get('http://membership-tenant.velora.test/__tenant-membership-test')
            ->assertForbidden();
    } finally {
        cleanupTenantCoreTestDatabase($path);
    }
});
