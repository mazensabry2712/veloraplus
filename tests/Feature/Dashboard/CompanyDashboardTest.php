<?php

use App\Models\PlatformAccount;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TenantMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function dashboardTestTenantDatabasePath(): string
{
    $directory = storage_path('framework/testing');

    File::ensureDirectoryExists($directory);

    $path = $directory.'/dashboard-'.Str::ulid().'.sqlite';
    touch($path);

    return $path;
}

function createDashboardTenant(string $path): Tenant
{
    $tenant = Tenant::factory()->create([
        'name' => 'Dashboard Tenant',
        'slug' => 'dashboard-tenant',
        'database_name' => $path,
        'database_host' => null,
        'database_port' => null,
        'status' => 'active',
        'database_status' => 'ready',
    ]);

    TenantDomain::create([
        'tenant_id' => $tenant->getKey(),
        'domain' => 'dashboard-tenant.velora.test',
        'type' => 'subdomain',
        'is_primary' => true,
        'status' => 'active',
        'verified_at' => now(),
    ]);

    return $tenant;
}

afterEach(function (): void {
    if (isset($this->dashboardTenantDatabasePath) && is_file($this->dashboardTenantDatabasePath)) {
        unlink($this->dashboardTenantDatabasePath);
    }
});

test('guest is redirected from the company dashboard', function (): void {
    $path = dashboardTestTenantDatabasePath();
    $this->dashboardTenantDatabasePath = $path;

    createDashboardTenant($path);

    $this->get('http://dashboard-tenant.velora.test/dashboard')
        ->assertRedirect('/login');
});

test('active tenant member can access the company dashboard', function (): void {
    $path = dashboardTestTenantDatabasePath();
    $this->dashboardTenantDatabasePath = $path;

    $tenant = createDashboardTenant($path);
    $member = PlatformAccount::factory()->create([
        'name' => 'Dashboard Member',
    ]);

    TenantMembership::create([
        'tenant_id' => $tenant->getKey(),
        'account_id' => $member->getKey(),
        'role_key' => 'owner',
        'status' => 'active',
        'joined_at' => now(),
    ]);

    $this->actingAs($member)
        ->get('http://dashboard-tenant.velora.test/dashboard')
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
        ->assertSee('Company Dashboard')
        ->assertSee('Dashboard Tenant')
        ->assertSee('Dashboard Member')
        ->assertSee('Owner');
});

test('account without an active tenant membership cannot access the dashboard', function (): void {
    $path = dashboardTestTenantDatabasePath();
    $this->dashboardTenantDatabasePath = $path;

    createDashboardTenant($path);
    $outsider = PlatformAccount::factory()->create();

    $this->actingAs($outsider)
        ->get('http://dashboard-tenant.velora.test/dashboard')
        ->assertForbidden();
});
