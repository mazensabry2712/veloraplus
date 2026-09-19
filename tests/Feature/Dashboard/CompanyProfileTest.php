<?php

use App\Application\Authorization\TenantRbacBootstrapper;
use App\Infrastructure\Tenancy\TenantDatabaseManager;
use App\Models\CompanySetting;
use App\Models\PlatformAccount;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TenantMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->originalTenantTemplate = config('database.connections.tenant_template');
});

function companyProfileTestTenantDatabasePath(): string
{
    $directory = storage_path('framework/testing');

    File::ensureDirectoryExists($directory);

    $path = $directory.'/company-profile-'.Str::ulid().'.sqlite';
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

function createCompanyProfileTenant(string $path): Tenant
{
    $tenant = Tenant::factory()->create([
        'name' => 'Profile Tenant',
        'legal_name' => 'Profile Tenant LLC',
        'slug' => 'profile-tenant',
        'industry' => 'healthcare',
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
        'domain' => 'profile-tenant.velora.test',
        'type' => 'subdomain',
        'is_primary' => true,
        'status' => 'active',
        'verified_at' => now(),
    ]);

    $manager = app(TenantDatabaseManager::class);
    $manager->connect($tenant);

    try {
        expect(\Illuminate\Support\Facades\Artisan::call('migrate', [
            '--database' => TenantDatabaseManager::CONNECTION,
            '--path' => 'database/migrations/tenant',
            '--force' => true,
        ]))->toBe(0);

        CompanySetting::query()->insert([
            [
                'id' => (string) Str::ulid(),
                'key' => 'company.name',
                'value' => $tenant->name,
                'type' => 'string',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::ulid(),
                'key' => 'company.legal_name',
                'value' => $tenant->legal_name,
                'type' => 'string',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::ulid(),
                'key' => 'company.country_code',
                'value' => $tenant->country_code,
                'type' => 'string',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::ulid(),
                'key' => 'company.default_currency',
                'value' => $tenant->default_currency,
                'type' => 'string',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::ulid(),
                'key' => 'company.timezone',
                'value' => $tenant->timezone,
                'type' => 'string',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::ulid(),
                'key' => 'company.locale',
                'value' => $tenant->locale,
                'type' => 'string',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    } finally {
        $manager->disconnect();
    }

    return $tenant;
}

afterEach(function (): void {
    DB::purge(TenantDatabaseManager::CONNECTION);
    DB::setDefaultConnection('central');

    config([
        'database.connections.tenant_template' => $this->originalTenantTemplate,
    ]);

    if (isset($this->companyProfileTenantDatabasePath) && is_file($this->companyProfileTenantDatabasePath)) {
        unlink($this->companyProfileTenantDatabasePath);
    }
});

test('tenant owner can update the company profile and its tenant settings projection', function (): void {
    $path = companyProfileTestTenantDatabasePath();
    $this->companyProfileTenantDatabasePath = $path;

    $tenant = createCompanyProfileTenant($path);
    $owner = PlatformAccount::factory()->create([
        'name' => 'Profile Owner',
    ]);

    TenantMembership::create([
        'tenant_id' => $tenant->getKey(),
        'account_id' => $owner->getKey(),
        'role_key' => 'owner',
        'status' => 'active',
        'joined_at' => now(),
    ]);

    app(TenantRbacBootstrapper::class)->bootstrapForTenant($tenant);

    $this->actingAs($owner)
        ->post('http://profile-tenant.velora.test/dashboard/company/profile', [
            'name' => 'Updated Clinic',
            'legal_name' => 'Updated Clinic Ltd',
            'industry' => 'beauty',
            'country_code' => 'EG',
            'default_currency' => 'EGP',
            'timezone' => 'Africa/Cairo',
            'locale' => 'ar-EG',
        ])
        ->assertRedirect('/dashboard')
        ->assertSessionHas('status', 'Company profile updated successfully.');

    $tenant->refresh();

    expect($tenant->name)->toBe('Updated Clinic')
        ->and($tenant->legal_name)->toBe('Updated Clinic Ltd')
        ->and($tenant->industry)->toBe('beauty')
        ->and($tenant->country_code)->toBe('EG')
        ->and($tenant->default_currency)->toBe('EGP')
        ->and($tenant->timezone)->toBe('Africa/Cairo')
        ->and($tenant->locale)->toBe('ar-EG');

    $manager = app(TenantDatabaseManager::class);
    $manager->connect($tenant);

    try {
        expect(CompanySetting::query()->where('key', 'company.name')->value('value'))->toBe('Updated Clinic')
            ->and(CompanySetting::query()->where('key', 'company.legal_name')->value('value'))->toBe('Updated Clinic Ltd')
            ->and(CompanySetting::query()->where('key', 'company.industry')->value('value'))->toBe('beauty')
            ->and(CompanySetting::query()->where('key', 'company.locale')->value('value'))->toBe('ar-EG');
    } finally {
        $manager->disconnect();
    }
});

test('tenant member without company update permission cannot update the company profile', function (): void {
    $path = companyProfileTestTenantDatabasePath();
    $this->companyProfileTenantDatabasePath = $path;

    $tenant = createCompanyProfileTenant($path);
    $member = PlatformAccount::factory()->create([
        'name' => 'Profile Staff',
    ]);

    TenantMembership::create([
        'tenant_id' => $tenant->getKey(),
        'account_id' => $member->getKey(),
        'role_key' => 'staff',
        'status' => 'active',
        'joined_at' => now(),
    ]);

    app(TenantRbacBootstrapper::class)->bootstrapForTenant($tenant);

    $this->actingAs($member)
        ->post('http://profile-tenant.velora.test/dashboard/company/profile', [
            'name' => 'Should Not Save',
            'legal_name' => null,
            'industry' => 'education',
            'country_code' => 'EG',
            'default_currency' => 'EGP',
            'timezone' => 'Africa/Cairo',
            'locale' => 'en',
        ])
        ->assertForbidden();

    expect($tenant->refresh()->name)->toBe('Profile Tenant');
});

test('company profile validation rejects malformed locale currency and timezone', function (): void {
    $path = companyProfileTestTenantDatabasePath();
    $this->companyProfileTenantDatabasePath = $path;

    $tenant = createCompanyProfileTenant($path);
    $owner = PlatformAccount::factory()->create();

    TenantMembership::create([
        'tenant_id' => $tenant->getKey(),
        'account_id' => $owner->getKey(),
        'role_key' => 'owner',
        'status' => 'active',
        'joined_at' => now(),
    ]);

    app(TenantRbacBootstrapper::class)->bootstrapForTenant($tenant);

    $this->actingAs($owner)
        ->post('http://profile-tenant.velora.test/dashboard/company/profile', [
            'name' => 'Updated Clinic',
            'legal_name' => null,
            'industry' => null,
            'country_code' => 'EG',
            'default_currency' => 'US',
            'timezone' => 'Not/A-Timezone',
            'locale' => 'bad locale',
        ])
        ->assertSessionHasErrors(['default_currency', 'timezone', 'locale']);

    expect($tenant->refresh()->name)->toBe('Profile Tenant');
});
