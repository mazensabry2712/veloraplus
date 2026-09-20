<?php

use App\Application\Authorization\TenantRbacBootstrapper;
use App\Infrastructure\Tenancy\TenantDatabaseManager;
use App\Models\Customer;
use App\Models\Location;
use App\Models\PlatformAccount;
use App\Models\Role;
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

function companyPagesDatabasePath(): string
{
    $directory = storage_path('framework/testing');

    File::ensureDirectoryExists($directory);

    $path = $directory.'/company-pages-'.Str::ulid().'.sqlite';
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

function companyPagesTenant(string $path): Tenant
{
    $tenant = Tenant::factory()->create([
        'name' => 'Company Pages Tenant',
        'slug' => 'company-pages',
        'country_code' => 'EG',
        'default_currency' => 'EGP',
        'timezone' => 'Africa/Cairo',
        'locale' => 'en',
        'database_name' => $path,
        'database_host' => null,
        'database_port' => null,
        'status' => 'active',
        'database_status' => 'ready',
    ]);

    TenantDomain::create([
        'tenant_id' => $tenant->getKey(),
        'domain' => 'company-pages.velora.test',
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

function companyPagesMember(Tenant $tenant, string $role): PlatformAccount
{
    $account = PlatformAccount::factory()->create();

    TenantMembership::create([
        'tenant_id' => $tenant->getKey(),
        'account_id' => $account->getKey(),
        'role_key' => $role,
        'status' => 'active',
        'joined_at' => now(),
    ]);

    app(TenantRbacBootstrapper::class)->bootstrapForTenant($tenant);

    return $account;
}

function companyPagesConnect(Tenant $tenant): void
{
    app(TenantDatabaseManager::class)->connect($tenant);
}

afterEach(function (): void {
    DB::purge(TenantDatabaseManager::CONNECTION);
    DB::setDefaultConnection('central');

    config([
        'database.connections.tenant_template' => $this->originalTenantTemplate,
    ]);

    if (isset($this->companyPagesDatabasePath) && is_file($this->companyPagesDatabasePath)) {
        unlink($this->companyPagesDatabasePath);
    }
});

test('owner can open every Company Dashboard management screen', function (): void {
    $path = companyPagesDatabasePath();
    $this->companyPagesDatabasePath = $path;

    $tenant = companyPagesTenant($path);
    $owner = companyPagesMember($tenant, 'owner');

    companyPagesConnect($tenant);

    try {
        $location = Location::factory()->create([
            'name' => 'Main Location',
            'status' => 'active',
            'timezone' => 'Africa/Cairo',
        ]);

        Staff::factory()->create([
            'name' => 'Main Staff',
            'location_id' => $location->getKey(),
            'status' => 'active',
        ]);

        Customer::factory()->create([
            'name' => 'Main Customer',
            'status' => 'active',
        ]);
    } finally {
        app(TenantDatabaseManager::class)->disconnect();
    }

    $this->actingAs($owner);

    $this->get('http://company-pages.velora.test/dashboard/company/profile')
        ->assertOk()
        ->assertSee('Company Profile', false)
        ->assertSee('Company Pages Tenant', false);

    $this->get('http://company-pages.velora.test/dashboard/company/locations')
        ->assertOk()
        ->assertSee('Main Location', false);

    $this->get('http://company-pages.velora.test/dashboard/company/staff')
        ->assertOk()
        ->assertSee('Main Staff', false)
        ->assertSee('Main Location', false);

    $this->get('http://company-pages.velora.test/dashboard/company/customers')
        ->assertOk()
        ->assertSee('Main Customer', false);

    $this->get('http://company-pages.velora.test/dashboard/company/users')
        ->assertOk()
        ->assertSee($owner->email, false)
        ->assertSee('Owner', false);

    $this->get('http://company-pages.velora.test/dashboard/company/roles')
        ->assertOk()
        ->assertSee('Owner', false)
        ->assertSee('System', false)
        ->assertDontSee('VeloraPlus platform', false);
});

test('staff can view company people pages but cannot open member administration', function (): void {
    $path = companyPagesDatabasePath();
    $this->companyPagesDatabasePath = $path;

    $tenant = companyPagesTenant($path);
    $staff = companyPagesMember($tenant, 'staff');

    $this->actingAs($staff)
        ->get('http://company-pages.velora.test/dashboard/company/profile')
        ->assertOk();

    $this->actingAs($staff)
        ->get('http://company-pages.velora.test/dashboard/company/locations')
        ->assertOk();

    $this->actingAs($staff)
        ->get('http://company-pages.velora.test/dashboard/company/staff')
        ->assertOk();

    $this->actingAs($staff)
        ->get('http://company-pages.velora.test/dashboard/company/customers')
        ->assertOk();

    $this->actingAs($staff)
        ->get('http://company-pages.velora.test/dashboard/company/users')
        ->assertForbidden();

    $this->actingAs($staff)
        ->get('http://company-pages.velora.test/dashboard/company/roles')
        ->assertForbidden();
});

test('customer listing is paginated instead of loading the full tenant collection', function (): void {
    $path = companyPagesDatabasePath();
    $this->companyPagesDatabasePath = $path;

    $tenant = companyPagesTenant($path);
    $owner = companyPagesMember($tenant, 'owner');

    companyPagesConnect($tenant);

    try {
        for ($i = 1; $i <= 21; $i++) {
            Customer::factory()->create([
                'name' => 'Customer '.$i,
                'created_at' => now()->addSeconds($i),
                'updated_at' => now()->addSeconds($i),
            ]);
        }
    } finally {
        app(TenantDatabaseManager::class)->disconnect();
    }

    $this->actingAs($owner)
        ->get('http://company-pages.velora.test/dashboard/company/customers')
        ->assertOk()
        ->assertSee('Customer 21', false)
        ->assertSee('Page 1 of 2', false);

    $this->actingAs($owner)
        ->get('http://company-pages.velora.test/dashboard/company/customers?page=2')
        ->assertOk()
        ->assertSee('Customer 1', false)
        ->assertSee('Page 2 of 2', false);
});


test('company management screens follow the dashboard locale', function (): void {
    $path = companyPagesDatabasePath();
    $this->companyPagesDatabasePath = $path;

    $tenant = companyPagesTenant($path);
    $owner = companyPagesMember($tenant, 'owner');

    $this->actingAs($owner)
        ->post('http://company-pages.velora.test/dashboard/preferences/locale', [
            'locale' => 'ar',
        ])
        ->assertRedirect();

    $this->actingAs($owner)
        ->get('http://company-pages.velora.test/dashboard/company/customers')
        ->assertOk()
        ->assertSee('قائمة العملاء', false)
        ->assertSee('إضافة عميل', false)
        ->assertDontSee('Customer List', false);

    $this->actingAs($owner)
        ->get('http://company-pages.velora.test/dashboard/company/locations')
        ->assertOk()
        ->assertSee('قائمة الفروع', false)
        ->assertSee('إضافة فرع', false);

    $this->actingAs($owner)
        ->get('http://company-pages.velora.test/dashboard/company/users')
        ->assertOk()
        ->assertSee('أعضاء الشركة', false)
        ->assertSee('إضافة مستخدم', false);

    $this->actingAs($owner)
        ->get('http://company-pages.velora.test/dashboard/company/roles')
        ->assertOk()
        ->assertSee('أدوار الشركة', false)
        ->assertSee('إنشاء دور مخصص', false);
});
