<?php

use App\Application\Booking\ServiceManager;
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


test('tenant public services expose only active online-bookable services with SEO metadata', function () {
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

    $services = app(ServiceManager::class);

    $public = $services->create([
        'name' => 'Dental Cleaning',
        'slug' => 'dental-cleaning',
        'description' => 'Routine dental cleaning service.',
        'seo_title' => 'Dental Cleaning | Velora Clinic',
        'seo_description' => 'Routine dental cleaning at Velora Clinic.',
        'social_image_url' => 'https://cdn.example.com/dental-cleaning.jpg',
        'duration_minutes' => 45,
        'price_minor' => 25000,
        'currency' => 'EGP',
        'deposit_amount_minor' => 0,
        'online_bookable' => true,
        'capacity' => 1,
    ]);

    $hidden = $services->create([
        'name' => 'Internal Service',
        'duration_minutes' => 30,
        'price_minor' => 15000,
        'currency' => 'EGP',
        'deposit_amount_minor' => 0,
        'online_bookable' => false,
        'capacity' => 1,
    ]);

    $manager->disconnect();

    $index = $this->get('https://clinic.velora.test/services');

    $index->assertSuccessful()
        ->assertSee('Dental Cleaning', false)
        ->assertDontSee('Internal Service')
        ->assertSee('<link rel="canonical" href="https://clinic.velora.test/services">', false);

    $show = $this->get('https://clinic.velora.test/services/dental-cleaning');

    $show->assertSuccessful()
        ->assertSee('<h1>Dental Cleaning</h1>', false)
        ->assertSee('<meta name="description" content="Routine dental cleaning at Velora Clinic.">', false)
        ->assertSee('<meta property="og:image" content="https://cdn.example.com/dental-cleaning.jpg">', false)
        ->assertSee('<link rel="canonical" href="https://clinic.velora.test/services/dental-cleaning">', false)
        ->assertSee('"@type":"Service"', false)
        ->assertSee('Dental Cleaning', false);

    $show->assertSee(
        '<a href="https://clinic.velora.test/book/dental-cleaning">Book this service</a>',
        false,
    );

    expect($public->slug)->toBe('dental-cleaning')
        ->and($hidden->online_bookable)->toBeFalse();
});


test('public booking entry is non-indexable and canonicalizes to the service page', function () {
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

    app(ServiceManager::class)->create([
        'name' => 'Dental Cleaning',
        'slug' => 'dental-cleaning',
        'duration_minutes' => 45,
        'price_minor' => 25000,
        'currency' => 'EGP',
        'deposit_amount_minor' => 0,
        'online_bookable' => true,
        'capacity' => 1,
    ]);

    $manager->disconnect();

    $response = $this->get('https://clinic.velora.test/book/dental-cleaning');

    $response->assertSuccessful()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertSee('<meta name="robots" content="noindex,nofollow">', false)
        ->assertSee('<link rel="canonical" href="https://clinic.velora.test/services/dental-cleaning">', false)
        ->assertSee('<h1>Book Dental Cleaning</h1>', false);
});

test('tenant sitemap includes only public service URLs', function () {
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

    app(ServiceManager::class)->create([
        'name' => 'Dental Cleaning',
        'duration_minutes' => 45,
        'price_minor' => 25000,
        'currency' => 'EGP',
        'deposit_amount_minor' => 0,
        'online_bookable' => true,
        'capacity' => 1,
    ]);

    app(ServiceManager::class)->create([
        'name' => 'Internal Service',
        'duration_minutes' => 30,
        'price_minor' => 15000,
        'currency' => 'EGP',
        'deposit_amount_minor' => 0,
        'online_bookable' => false,
        'capacity' => 1,
    ]);

    $manager->disconnect();

    $sitemap = $this->get('https://clinic.velora.test/sitemap.xml');

    $sitemap->assertSuccessful()
        ->assertSee('<loc>https://clinic.velora.test/</loc>', false)
        ->assertSee('<loc>https://clinic.velora.test/services</loc>', false)
        ->assertSee('/services/dental-cleaning', false)
        ->assertDontSee('/services/internal-service', false);
});

test('published service slug changes redirect permanently to the current canonical slug', function () {
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

    $service = app(ServiceManager::class)->create([
        'name' => 'Dental Cleaning',
        'duration_minutes' => 45,
        'price_minor' => 25000,
        'currency' => 'EGP',
        'deposit_amount_minor' => 0,
        'online_bookable' => true,
        'capacity' => 1,
    ]);

    $oldSlug = $service->slug;

    app(ServiceManager::class)->update($service, [
        'slug' => 'professional-dental-cleaning',
    ]);

    $manager->disconnect();

    $response = $this->get('https://clinic.velora.test/services/'.$oldSlug);

    $response->assertRedirect('https://clinic.velora.test/services/professional-dental-cleaning')
        ->assertStatus(301);

    TenantDomain::query()->create([
        'tenant_id' => $tenant->getKey(),
        'domain' => 'alternate.velora.test',
        'type' => 'subdomain',
        'is_primary' => false,
        'status' => 'active',
    ]);

    $alternate = $this->get('https://alternate.velora.test/services/'.$oldSlug);

    $alternate->assertRedirect('https://clinic.velora.test/services/professional-dental-cleaning')
        ->assertStatus(301);
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
