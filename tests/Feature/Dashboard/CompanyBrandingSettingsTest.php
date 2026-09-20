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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->originalTenantTemplate = config('database.connections.tenant_template');
    $this->companyBrandingPaths = [];
    Storage::fake('public');
});

function companyBrandingTestPath(string $suffix = ''): string
{
    $directory = storage_path('framework/testing');

    File::ensureDirectoryExists($directory);

    $path = $directory.'/company-branding-'.Str::ulid().$suffix.'.sqlite';
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

function createCompanyBrandingTenant(string $path, string $domain = 'branding-tenant.velora.test', string $slug = 'branding-tenant'): Tenant
{
    $tenant = Tenant::factory()->create([
        'name' => 'Branding Tenant',
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

    app(TenantDatabaseManager::class)->connect($tenant);

    try {
        expect(Artisan::call('migrate', [
            '--database' => TenantDatabaseManager::CONNECTION,
            '--path' => 'database/migrations/tenant',
            '--force' => true,
        ]))->toBe(0);
    } finally {
        app(TenantDatabaseManager::class)->disconnect();
    }

    return $tenant;
}

function addBrandingMember(Tenant $tenant, string $roleKey = 'owner'): PlatformAccount
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

function brandingSettingValue(Tenant $tenant, string $key): ?string
{
    app(TenantDatabaseManager::class)->connect($tenant);

    try {
        return CompanySetting::query()->where('key', $key)->value('value');
    } finally {
        app(TenantDatabaseManager::class)->disconnect();
    }
}

afterEach(function (): void {
    DB::purge(TenantDatabaseManager::CONNECTION);
    DB::setDefaultConnection('central');

    config([
        'database.connections.tenant_template' => $this->originalTenantTemplate,
    ]);

    foreach ($this->companyBrandingPaths as $path) {
        if (is_file($path)) {
            unlink($path);
        }
    }
});

test('authorized tenant member can save branding assets and color tokens', function (): void {
    $path = companyBrandingTestPath();
    $this->companyBrandingPaths[] = $path;

    $tenant = createCompanyBrandingTenant($path);
    $owner = addBrandingMember($tenant);

    $this->actingAs($owner)
        ->put('http://branding-tenant.velora.test/dashboard/company/branding', [
            'primary_color' => '#2563EB',
            'secondary_color' => '#0F172A',
            'accent_color' => '#F59E0B',
            'background_color' => '#FFFFFF',
            'text_color' => '#111827',
            'logo' => UploadedFile::fake()->image('logo.png', 800, 300),
            'favicon' => UploadedFile::fake()->image('favicon.png', 128, 128),
        ])
        ->assertRedirect('/dashboard')
        ->assertSessionHas('status', 'Company branding updated successfully.');

    expect(brandingSettingValue($tenant, 'branding.primary_color'))->toBe('#2563EB')
        ->and(brandingSettingValue($tenant, 'branding.secondary_color'))->toBe('#0F172A')
        ->and(brandingSettingValue($tenant, 'branding.logo_path'))->not->toBeNull()
        ->and(brandingSettingValue($tenant, 'branding.favicon_path'))->not->toBeNull();

    $manager = app(TenantDatabaseManager::class);
    $manager->connect($tenant);

    try {
        $branding = app(\App\Application\Company\CompanyBrandingManager::class)->branding();

        expect($branding['logo_url'])->not->toBeNull()
            ->and($branding['favicon_url'])->not->toBeNull();
    } finally {
        $manager->disconnect();
    }
});

test('member without settings permission cannot change company branding', function (): void {
    $path = companyBrandingTestPath();
    $this->companyBrandingPaths[] = $path;

    $tenant = createCompanyBrandingTenant($path);
    $staff = addBrandingMember($tenant, 'staff');

    $this->actingAs($staff)
        ->put('http://branding-tenant.velora.test/dashboard/company/branding', [
            'primary_color' => '#FFFFFF',
        ])
        ->assertForbidden();
});

test('social media and tax settings are tenant-scoped and validated', function (): void {
    $path = companyBrandingTestPath();
    $this->companyBrandingPaths[] = $path;

    $tenant = createCompanyBrandingTenant($path);
    $owner = addBrandingMember($tenant);

    $this->actingAs($owner)
        ->put('http://branding-tenant.velora.test/dashboard/company/settings', [
            'social' => [
                'facebook' => 'https://facebook.com/branding-tenant',
                'instagram' => 'https://instagram.com/branding-tenant',
                'linkedin' => 'https://linkedin.com/company/branding-tenant',
                'youtube' => 'https://youtube.com/@branding-tenant',
                'tiktok' => 'https://tiktok.com/@branding-tenant',
                'x' => 'https://x.com/branding-tenant',
                'whatsapp' => 'https://wa.me/201001112233',
            ],
            'tax' => [
                'enabled' => true,
                'rate_bps' => 1450,
                'registration_number' => 'VAT-123',
            ],
        ])
        ->assertRedirect('/dashboard')
        ->assertSessionHas('status', 'Tenant settings updated successfully.');

    expect(brandingSettingValue($tenant, 'social.instagram'))->toBe('https://instagram.com/branding-tenant')
        ->and(brandingSettingValue($tenant, 'social.whatsapp'))->toBe('https://wa.me/201001112233')
        ->and(brandingSettingValue($tenant, 'tax.enabled'))->toBe('1')
        ->and(brandingSettingValue($tenant, 'tax.rate_bps'))->toBe('1450')
        ->and(brandingSettingValue($tenant, 'tax.registration_number'))->toBe('VAT-123');

    $this->actingAs($owner)
        ->put('http://branding-tenant.velora.test/dashboard/company/settings', [
            'social' => [
                'instagram' => 'not-a-url',
            ],
            'tax' => [
                'rate_bps' => 10001,
            ],
        ])
        ->assertSessionHasErrors(['social.instagram', 'tax.rate_bps']);
});

test('social settings remain isolated between tenant databases', function (): void {
    $pathA = companyBrandingTestPath('-a');
    $pathB = companyBrandingTestPath('-b');
    $this->companyBrandingPaths = [$pathA, $pathB];

    $tenantA = createCompanyBrandingTenant($pathA, 'branding-a.velora.test', 'branding-a');
    $tenantB = createCompanyBrandingTenant($pathB, 'branding-b.velora.test', 'branding-b');

    $ownerA = addBrandingMember($tenantA);
    $ownerB = addBrandingMember($tenantB);

    $this->actingAs($ownerB)
        ->put('http://branding-b.velora.test/dashboard/company/settings', [
            'social' => [
                'instagram' => 'https://instagram.com/tenant-b',
            ],
        ])
        ->assertRedirect('/dashboard');

    $this->actingAs($ownerA)
        ->put('http://branding-a.velora.test/dashboard/company/settings', [
            'social' => [
                'instagram' => 'https://instagram.com/tenant-a',
            ],
        ])
        ->assertRedirect('/dashboard');

    expect(brandingSettingValue($tenantA, 'social.instagram'))->toBe('https://instagram.com/tenant-a')
        ->and(brandingSettingValue($tenantB, 'social.instagram'))->toBe('https://instagram.com/tenant-b');
});

test('public tenant home consumes company branding and social settings', function (): void {
    $path = companyBrandingTestPath();
    $this->companyBrandingPaths[] = $path;

    $tenant = createCompanyBrandingTenant($path);

    $manager = app(TenantDatabaseManager::class);
    $manager->connect($tenant);

    try {
        CompanySetting::query()->create([
            'key' => 'branding.primary_color',
            'value' => '#2563EB',
            'type' => 'string',
        ]);

        CompanySetting::query()->create([
            'key' => 'branding.logo_path',
            'value' => 'tenants/'.$tenant->getKey().'/branding/logo.png',
            'type' => 'string',
        ]);

        CompanySetting::query()->create([
            'key' => 'social.instagram',
            'value' => 'https://instagram.com/branding-tenant',
            'type' => 'string',
        ]);
    } finally {
        $manager->disconnect();
    }

    $this->get('http://branding-tenant.velora.test/')
        ->assertOk()
        ->assertSee('Branding Tenant', false)
        ->assertSee('Instagram', false)
        ->assertSee('https://instagram.com/branding-tenant', false)
        ->assertSee('--brand-primary: #2563EB', false)
        ->assertSee('tenants/'.$tenant->getKey().'/branding/logo.png', false);
});

test('tenant settings manager exposes typed tax and social namespaces', function (): void {
    $path = companyBrandingTestPath();
    $this->companyBrandingPaths[] = $path;

    $tenant = createCompanyBrandingTenant($path);

    $manager = app(TenantDatabaseManager::class);
    $manager->connect($tenant);

    try {
        CompanySetting::query()->create([
            'key' => 'social.instagram',
            'value' => 'https://instagram.com/branding-tenant',
            'type' => 'string',
        ]);

        CompanySetting::query()->create([
            'key' => 'tax.enabled',
            'value' => '1',
            'type' => 'boolean',
        ]);

        CompanySetting::query()->create([
            'key' => 'tax.rate_bps',
            'value' => '1450',
            'type' => 'integer',
        ]);

        $settings = app(TenantSettingsManager::class);

        expect($settings->social()['instagram'])->toBe('https://instagram.com/branding-tenant')
            ->and($settings->tax()['enabled'])->toBe('1')
            ->and($settings->tax()['rate_bps'])->toBe('1450');
    } finally {
        $manager->disconnect();
    }
});
