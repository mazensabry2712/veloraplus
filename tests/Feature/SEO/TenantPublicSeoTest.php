<?php

use App\Application\SEO\SeoManager;
use App\Domain\Tenancy\TenantContext;
use App\Infrastructure\Tenancy\TenantDatabaseManager;
use App\Models\CompanySetting;
use App\Models\Tenant;
use App\Models\TenantDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

afterEach(function () {
    DB::purge(TenantDatabaseManager::CONNECTION);
    app(TenantContext::class)->clear();

    if (isset($this->seoTenantDatabases)) {
        foreach ($this->seoTenantDatabases as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
});

function seoTenantDatabase(): string
{
    $directory = storage_path('framework/testing');

    File::ensureDirectoryExists($directory);

    $path = $directory.'/seo-'.Str::ulid().'.sqlite';

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

function migrateSeoTenantDatabase(string $path): void
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

function createSeoTenant(string $slug, string $name, string $primaryDomain, string $path): Tenant
{
    $tenant = Tenant::factory()->create([
        'name' => $name,
        'slug' => $slug,
        'database_name' => $path,
        'database_status' => 'ready',
        'status' => 'active',
    ]);

    TenantDomain::query()->create([
        'tenant_id' => $tenant->getKey(),
        'domain' => $primaryDomain,
        'type' => 'subdomain',
        'is_primary' => true,
        'status' => 'active',
    ]);

    migrateSeoTenantDatabase($path);

    return $tenant;
}

test('tenant public home resolves from the request host and renders tenant SEO metadata', function () {
    $path = seoTenantDatabase();
    $this->seoTenantDatabases = [$path];

    $tenant = createSeoTenant(
        slug: 'clinic',
        name: 'Velora Clinic',
        primaryDomain: 'clinic.velora.test',
        path: $path,
    );

    config(['velora.tenancy.base_domain' => 'velora.test']);

    $manager = app(TenantDatabaseManager::class);
    $manager->connect($tenant);

    CompanySetting::query()->create([
        'key' => 'seo.site_title',
        'value' => 'Velora Clinic | Dental & Medical Care',
    ]);

    CompanySetting::query()->create([
        'key' => 'seo.site_description',
        'value' => 'Dental and medical services from Velora Clinic.',
    ]);

    CompanySetting::query()->create([
        'key' => 'seo.locale',
        'value' => 'en_US',
    ]);

    $manager->disconnect();

    $response = $this->get('https://clinic.velora.test/');

    $response->assertSuccessful()
        ->assertSee('<h1>Velora Clinic</h1>', false)
        ->assertSee('<title>Velora Clinic | Dental &amp; Medical Care</title>', false)
        ->assertSee('<meta name="description" content="Dental and medical services from Velora Clinic.">', false)
        ->assertSee('<meta name="robots" content="index,follow">', false)
        ->assertSee('<meta property="og:site_name" content="Velora Clinic">', false)
        ->assertSee('<link rel="canonical" href="https://clinic.velora.test/">', false)
        ->assertSee('<script type="application/ld+json">', false)
        ->assertSee('Velora Clinic', false);
});

test('tenant canonical url always uses the active primary domain', function () {
    $path = seoTenantDatabase();
    $this->seoTenantDatabases = [$path];

    $tenant = createSeoTenant(
        slug: 'clinic',
        name: 'Velora Clinic',
        primaryDomain: 'clinic.velora.test',
        path: $path,
    );

    TenantDomain::query()->create([
        'tenant_id' => $tenant->getKey(),
        'domain' => 'alternate.velora.test',
        'type' => 'subdomain',
        'is_primary' => false,
        'status' => 'active',
    ]);

    config(['velora.tenancy.base_domain' => 'velora.test']);

    $response = $this->get('https://alternate.velora.test/');

    $response->assertSuccessful()
        ->assertSee('<link rel="canonical" href="https://clinic.velora.test/">', false)
        ->assertSee('<meta property="og:url" content="https://clinic.velora.test/">', false);
});

test('tenant robots and sitemap use the tenant canonical domain', function () {
    $path = seoTenantDatabase();
    $this->seoTenantDatabases = [$path];

    $tenant = createSeoTenant(
        slug: 'clinic',
        name: 'Velora Clinic',
        primaryDomain: 'clinic.velora.test',
        path: $path,
    );

    config(['velora.tenancy.base_domain' => 'velora.test']);

    $robots = $this->get('https://clinic.velora.test/robots.txt');

    $robots->assertSuccessful()
        ->assertSee('Allow: /')
        ->assertSee('Sitemap: https://clinic.velora.test/sitemap.xml');

    $sitemap = $this->get('https://clinic.velora.test/sitemap.xml');

    $sitemap->assertSuccessful()
        ->assertSee('<loc>https://clinic.velora.test/</loc>', false)
        ->assertDontSee('alternate.velora.test');
});

test('unknown tenant host cannot reach the public home', function () {
    config(['velora.platform.url' => 'https://velora.com']);

    $this->get('https://unknown.velora.test/')
        ->assertNotFound();
});

test('tenant public pages are isolated by exact hostname', function () {
    $firstPath = seoTenantDatabase();
    $secondPath = seoTenantDatabase();
    $this->seoTenantDatabases = [$firstPath, $secondPath];

    createSeoTenant(
        slug: 'clinic-a',
        name: 'Clinic A',
        primaryDomain: 'clinic-a.velora.test',
        path: $firstPath,
    );

    createSeoTenant(
        slug: 'clinic-b',
        name: 'Clinic B',
        primaryDomain: 'clinic-b.velora.test',
        path: $secondPath,
    );

    config(['velora.tenancy.base_domain' => 'velora.test']);

    $response = $this->get('https://clinic-a.velora.test/');

    $response->assertSuccessful()
        ->assertSee('<h1>Clinic A</h1>', false)
        ->assertDontSee('Clinic B');
});
