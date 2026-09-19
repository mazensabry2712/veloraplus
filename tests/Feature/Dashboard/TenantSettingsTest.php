<?php

use App\Application\Authorization\TenantRbacBootstrapper;
use App\Application\Company\TenantSettingsManager;
use App\Infrastructure\Tenancy\TenantDatabaseManager;
use App\Models\CompanySetting;
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

function tenantSettingsTestDatabasePath(): string
{
    $directory = storage_path('framework/testing');

    File::ensureDirectoryExists($directory);

    $path = $directory.'/tenant-settings-'.Str::ulid().'.sqlite';
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

function createTenantSettingsTenant(
    string $path,
    string $domain = 'settings-tenant.velora.test',
    string $slug = 'settings-tenant',
): Tenant {
    $tenant = Tenant::factory()->create([
        'name' => 'Settings Tenant',
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

function addTenantSettingsMember(Tenant $tenant, string $roleKey = 'owner'): PlatformAccount
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

    if (isset($this->tenantSettingsTestDatabasePath) && is_file($this->tenantSettingsTestDatabasePath)) {
        unlink($this->tenantSettingsTestDatabasePath);
    }
});

test('authorized tenant member can update SEO tenant settings', function (): void {
    $path = tenantSettingsTestDatabasePath();
    $this->tenantSettingsTestDatabasePath = $path;

    $tenant = createTenantSettingsTenant($path);
    $owner = addTenantSettingsMember($tenant);

    $this->actingAs($owner)
        ->put('http://settings-tenant.velora.test/dashboard/company/settings', [
            'seo' => [
                'site_title' => 'Settings Tenant | Official',
                'site_description' => 'Official public site for Settings Tenant.',
                'default_og_image' => 'https://cdn.example.com/settings.jpg',
                'robots' => 'index,follow',
                'locale' => 'en_US',
            ],
        ])
        ->assertRedirect('/dashboard')
        ->assertSessionHas('status', 'Tenant settings updated successfully.');

    $manager = app(TenantDatabaseManager::class);
    $manager->connect($tenant);

    try {
        expect(CompanySetting::query()->where('key', 'seo.site_title')->value('value'))
            ->toBe('Settings Tenant | Official')
            ->and(CompanySetting::query()->where('key', 'seo.site_description')->value('value'))
            ->toBe('Official public site for Settings Tenant.')
            ->and(CompanySetting::query()->where('key', 'seo.default_og_image')->value('value'))
            ->toBe('https://cdn.example.com/settings.jpg')
            ->and(CompanySetting::query()->where('key', 'seo.locale')->value('value'))
            ->toBe('en_US');
    } finally {
        $manager->disconnect();
    }
});

test('tenant member without settings manage permission cannot update tenant settings', function (): void {
    $path = tenantSettingsTestDatabasePath();
    $this->tenantSettingsTestDatabasePath = $path;

    $tenant = createTenantSettingsTenant($path);
    $staff = addTenantSettingsMember($tenant, 'staff');

    $this->actingAs($staff)
        ->put('http://settings-tenant.velora.test/dashboard/company/settings', [
            'seo' => [
                'site_title' => 'Should Not Save',
            ],
        ])
        ->assertForbidden();
});

test('tenant settings validation rejects unknown and malformed SEO values', function (): void {
    $path = tenantSettingsTestDatabasePath();
    $this->tenantSettingsTestDatabasePath = $path;

    $tenant = createTenantSettingsTenant($path);
    $owner = addTenantSettingsMember($tenant);

    $this->actingAs($owner)
        ->put('http://settings-tenant.velora.test/dashboard/company/settings', [
            'seo' => [
                'site_title' => str_repeat('x', 181),
                'robots' => 'allow-all',
                'locale' => 'not a locale',
                'unexpected' => 'must fail',
            ],
        ])
        ->assertSessionHasErrors(['seo', 'seo.site_title', 'seo.robots', 'seo.locale']);

    $manager = app(TenantDatabaseManager::class);
    $manager->connect($tenant);

    try {
        expect(CompanySetting::query()->where('key', 'seo.site_title')->exists())->toBeFalse();
    } finally {
        $manager->disconnect();
    }
});

test('tenant settings remain isolated between tenant databases', function (): void {
    $pathA = tenantSettingsTestDatabasePath();
    $pathB = tenantSettingsTestDatabasePath();
    $this->tenantSettingsTestDatabasePath = $pathA;

    $tenantA = createTenantSettingsTenant($pathA, 'settings-a.velora.test', 'settings-a');
    $tenantB = createTenantSettingsTenant($pathB, 'settings-b.velora.test', 'settings-b');

    $ownerA = addTenantSettingsMember($tenantA);

    $manager = app(TenantDatabaseManager::class);
    $manager->connect($tenantB);

    try {
        CompanySetting::query()->create([
            'key' => 'seo.site_title',
            'value' => 'Tenant B Title',
            'type' => 'string',
        ]);
    } finally {
        $manager->disconnect();
        DB::purge(TenantDatabaseManager::CONNECTION);
    }

    $this->actingAs($ownerA)
        ->put('http://settings-tenant.velora.test/dashboard/company/settings', [
            'seo' => [
                'site_title' => 'Tenant A Title',
            ],
        ])
        ->assertRedirect('/dashboard');

    $manager->connect($tenantA);

    try {
        expect(CompanySetting::query()->where('key', 'seo.site_title')->value('value'))
            ->toBe('Tenant A Title');
    } finally {
        $manager->disconnect();
    }

    DB::purge(TenantDatabaseManager::CONNECTION);
    if (is_file($pathB)) {
        unlink($pathB);
    }
});
